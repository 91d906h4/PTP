<?php
    // Header
    session_start();
    ini_set("display_errors", 0);
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: *");
    header("Access-Control-Allow-Headers: *");

    // Include
    require_once("./src/functions.php");

    // Initializing
    // initialize();

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $connect = json_decoder("php://input");
        if(!empty($connect)){
            $connection_code = $connect["CONNECTION_CODE"];
    
            message("e", "Remote node ".$connect["CONNECTOR"]." connected.");
    
            if($connection_code == "PT-0301"){
                $file_id = $connect["DATA"]["FILE_ID"];
                $split = $connect["DATA"]["REQUEST_SPLIT"];
                if(empty($split)){
                    message("w", "No split requested.");
                    die();
                }
                $temp = json_decoder("./conf/nodes.json")[$connect["CONNECTOR"]];
                $password = $temp["PASSWORD"];
                $iv = base64_decode($temp["IV"]);
                echo encrypt(file_get_contents("./seed/".$file_id."/".$split.".ptp"), $password, $iv);
                die();
            }
            elseif($connection_code == "PT-0302"){
                $file_id = $connect["DATA"]["FILE_ID"];
                $centers = json_decoder("./conf/centers.json");
                async_connect($centers, "PT-0109", array("NODE" => $connect["CONNECTOR"], "FILE_ID" => $file_id, "SPLIT" => $connect["DATA"]["REQUEST_SPLIT"]));
                die();
            }
            elseif($connection_code == "PT-0309.1"){
                $file = "./conf/nodes.json";
                $nodes = json_decoder($file);
                $password = base64_encode(openssl_random_pseudo_bytes(16));
                $iv = base64_encode(openssl_random_pseudo_bytes(16));
                $nodes[$connect["CONNECTOR"]]["PUBLIC_KEY"] = $connect["DATA"]["PUBLIC_KEY"];
                $nodes[$connect["CONNECTOR"]]["PASSWORD"] = $password;
                $nodes[$connect["CONNECTOR"]]["IV"] = $iv;
                json_file_put_contents($file, $nodes);
                $data = pb_encrypt(json_encode(array("PASSWORD" => $password, "IV" => $iv)), "public", $connect["DATA"]["PUBLIC_KEY"]);
                echo json_encode(array("DATA" => $data));
                die();
            }
        }
    }

    if(isset($_POST["upload"])){
        if(!$_FILES["upload_file"]["name"]){message("w", "No file uploaded.");}
        else{
            $file_id = hash("sha512", substr(str_shuffle("01234567890123456789aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ"), 0, 64));

            // Upload file to ./seed/
            $dir = "./seed/".$file_id."/";
            mkdir($dir);
            $splits = array();

            // Split file
            message("i", "Processing file ...");
            $filename = $_FILES["upload_file"]["tmp_name"];
            $fileSize = $_FILES["upload_file"]["size"];
            $handle = fopen($filename, "r");
            $chuckSize = (1 << 17); // 128KB
            $position = 0;
            $index = 0;
            
            if($handle){
                while(!feof($handle)){
                    if($chuckSize === 0) break;

                    // Split file
                    $chunk = fread($handle, $chuckSize);

                    // Generate splite name with hash
                    $tempname = $index."-".hash("sha256", strval($chunk));

                    // Generate split list
                    array_push($splits, $tempname);

                    // Generate split
                    file_put_contents($dir.$tempname.".ptp", $chunk);

                    $index++;
                    $position += strlen($chunk);
                    if($position + $chuckSize > $fileSize){$chuckSize = $fileSize - $position;}
                    fseek($handle, $position);
                }
                fclose($handle);
            }

            $local_node = json_decoder("./conf/config.json")["LOCAL_NODE"];

            message("i", "Update seed information to centers ...");
            $centers = json_decoder("./conf/centers.json");
            $new_center_list = array();

            message("i", "Connecting to centers ...");
            $connect = connect($centers, "PT-0105", array("FILE_ID" => $file_id, "SPLITS" => $splits));

            foreach($centers as $center){
                if($connect[$center]["STATUS"] == "OK"){
                    message("i", "Uploading to center ".$center." successed.");
                    $new_center_list = array_values(array_unique(array_merge($new_center_list, [$center], $connect[$center]["CENTERS"])));
                }
                else{
                    unset($centers[$center]);
                    message("i", "Uploading to center ".$center." falied. Error message : ".$connect[$center]["ERROR_MESSAGE"] ?: "Remote center didn't response.");
                }
            }

            $file_name = $_FILES["upload_file"]["name"];
            json_file_put_contents("./seed_".$file_name.".json", array("FILE_ID" => $file_id, "FILE_NAME" => $file_name, "CENTERS" => $new_center_list, "SPLITS" => $splits));
            message("i", "Seed of file ".$file_name." generation successed.");
        }
    }

    if(isset($_POST["seed"])){
        if(!$_FILES["seed"]["name"]){message("w", "No seed file selected.");}
        else{
            $seed = json_decoder($_FILES["seed"]["tmp_name"]);
            $file_id = $seed["FILE_ID"];

            message("i", "Connecting to centers ...");
            $connect = connect($seed["CENTERS"], "PT-0106", array("FILE_ID" => $file_id));

            foreach($seed["CENTERS"] as $center){
                if($connect[$center]["STATUS"] == "OK"){
                    json_file_put_contents("./temp/temp_".$file_id.".json", $connect[$center]["DATA"]);
                    message("i", "Get file ".$file_id." successed.");
                    break;
                }
                else{
                    message("w", "Get file ".$file_id." from center ".$center." falied. Error message : ".$connect[$center]["ERROR_MESSAGE"] ?: "Remote center didn't response.");
                }
            }

            download($seed);
        }
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <title>P2TOR</title>
    </head>
    <body>
        <form action="./" method="POST" enctype="multipart/form-data">
            <fieldset><legend>Upload file</legend><input type="file" name="upload_file" /> <input type="submit" name="upload" value="Upload" /></fieldset><br>
            <fieldset><legend>Download file</legend><input type="file" name="seed" /> <input type="submit" name="seed" value="Open seed" /></fieldset><br>
        </form>
    </body>
</html>