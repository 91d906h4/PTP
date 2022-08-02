<?php
    // Header
    session_start();
    ini_set("display_errors", 0);
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: *");
    header("Access-Control-Allow-Headers: *");

    // Include
    require_once("./rsc/functions.php");

    // Setting
    $setting = parse_ini_file("./conf/p2tor.ini");

    // Initializing
    initialize();

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $connect = json_decoder("php://input");
        $file_id = $connect["DATA"]["FILE_ID"];
        error_log("[P2TOR] -Info- Remote node ".$connect["CONNECTOR"]." connected.");
        if($connect["CONNECTION_CODE"] == "PT-0105"){
            error_log("[P2TOR] -Info- Upload file ".$file_id.".");
            try{
                $file = "./database/".$file_id.".json";
                $data = array($connect["CONNECTOR"] => $connect["DATA"]["SPLITS"]);
                json_file_put_contents($file, $data);
                $centers = json_decoder("./conf/centers.json");
                foreach($centers as $center){if(str_starts_with($center, "-")){unset($centers[$center]);}}
                echo json_encode(array("STATUS" => "OK", "CENTERS" => $centers));
                error_log("[P2TOR] -Info- Upload file ".$file_id." successed.");
            }
            catch(\Throwable $th){
                error_log("[P2TOR] -Waring- Upload file ".$file_id." falied.");
                echo json_encode(array("STATUS" => "FAILED", "ERROR_MESSAGE" => $th));
            }
            update_database($file_id);
            die();
        }
        elseif($connect["CONNECTION_CODE"] == "PT-0106"){
            error_log("[P2TOR] -Info- Request for file ".$file_id.".");
            try{
                $file = "./database/".$file_id.".json";
                $json = json_decoder($file);
                echo json_encode(array("STATUS" => "OK", "DATA" => $json));
                error_log("[P2TOR] -Info- Return request of file ".$file_id." successed.");
                $json[$connect["CONNECTOR"]] = [];
                json_file_put_contents($file, $json);
            }
            catch(\Throwable $th){
                error_log("[P2TOR] -Waring- Return request of file ".$file_id." falied.");
                echo json_encode(array("STATUS" => "FAILED", "ERROR_MESSAGE" => $th));
            }
            update_database($file_id);
            die();
        }
        elseif($connect["CONNECTION_CODE"] == "PT-0109"){
            error_log("[P2TOR] -Info- Update file ".$file_id.".");
            try{
                $file = "./database/".$file_id.".json";
                $json = json_decoder($file);
                echo json_encode(array("STATUS" => "OK"));
                error_log("[P2TOR] -Info- Update file ".$file_id." successed.");
                $json = json_merge($json, array($connect["DATA"]["NODE"] => [$connect["DATA"]["SPLIT"]]));
                json_file_put_contents($file, $json);
            }
            catch(\Throwable $th){
                error_log("[P2TOR] -Waring- Update file ".$file_id." falied.");
                echo json_encode(array("STATUS" => "FAILED", "ERROR_MESSAGE" => $th));
            }
            update_database($file_id);
            die();
        }
        elseif($connect["CONNECTION_CODE"] == "PT-0401"){
            error_log("[P2TOR] -Info- Update from center ".$connect["CONNECTOR"].".");
            try{
                $file = "./database/".$file_id.".json";
                if(file_exists($file)){
                    $local_data = json_decoder($file);
                    $remote_data = $connect["DATA"]["CONTANTS"];

                    echo json_encode(array("STATUS" => "OK", "DATA" => $local_data));
                    error_log("[P2TOR] -Info- Return local file successed.");

                    $new_json = json_merge($local_data, $remote_data);
                    json_file_put_contents($file, $new_json);
                }
                else{
                    json_file_put_contents($file, $connect["DATA"]["CONTANTS"]);
                }
            }
            catch(\Throwable $th){
                error_log("[P2TOR] -Waring- Update file ".$file_id." falied. $th");
                echo json_encode(array("STATUS" => "FAILED", "ERROR_MESSAGE" => $th));
            }
            update_database($file_id);
            die();
        }
    }
?>