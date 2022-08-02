<?php
    // Include
    require_once("./src/onion_generator.php");

    // Setting
    $setting = parse_ini_file("./conf/ptp.ini");
    set_time_limit(0);

    // Function : initialize
    // Description :  Local node initialize
    function initialize(){
        global $setting;
        $init_file = "./conf/config.json";
        if(!file_exists($init_file)){
            message("e", "Conf file doesn't exists.");
            die();
        }
        $json = json_decoder($init_file);

        $tornado = new Tornado(array('onionSaveDir' => $setting["HIDDEN_SERVICE_DIR"], 'overridePermissions' => true));
        $onioin = $tornado -> generateAddress(1);

        $json["LOCAL_NODE"] = $onioin[0]["address"];

        // Generate RSA key pair
        $config = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 4096,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );
        $RSA = openssl_pkey_new($config);
        openssl_pkey_export($RSA, $private_key);
        $public_key = openssl_pkey_get_details($RSA)["key"];

        $json["PUBLIC_KEY"] = base64_encode($public_key);
        $json["PRIVATE_KEY"] = base64_encode($public_key);

        json_file_put_contents($init_file, $json);
    }

    // Function : connect
    // Description : Connect to remote node.
    function connect($nodes, $code, $data, $json_decode = true, $array_type = ""){
        global $setting;
        $local_node_info = json_decoder("./conf/config.json");

        $sending = array(
            "CONNECTOR" => $local_node_info["LOCAL_NODE"],
            "CONNECTION_CODE" => $code,
            "DATA" => $data
        );

        $mh = curl_multi_init();
        $curl_array = array();
        $result = array();

        foreach($nodes as $node){
            if($array_type == "PT-0301" || $array_type == "PT-0302"){
                $node_key = $node;
                $temp = explode(";", $node);
                $node = $temp[0];
                $sending["DATA"] = array("FILE_ID" => $data, "REQUEST_SPLIT" => $temp[1]);
            }
            else{$node_key = $node;}

            if(str_starts_with($node, "-")){continue;}
            $curl_array[$node_key] = curl_init();
            curl_setopt($curl_array[$node_key], CURLOPT_URL, $node);
            curl_setopt($curl_array[$node_key], CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl_array[$node_key], CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($curl_array[$node_key], CURLOPT_POSTFIELDS, json_encode($sending));
            curl_setopt($curl_array[$node_key], CURLOPT_POSTREDIR, 3);
            curl_setopt($curl_array[$node_key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_array[$node_key], CURLOPT_PROXY, $setting["TOR_PROXY"]);
            curl_setopt($curl_array[$node_key], CURLOPT_PROXYTYPE, 7);
            curl_setopt($curl_array[$node_key], CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl_array[$node_key], CURLOPT_TIMEOUT, $setting["CONNECT_TIME_OUT"]);
            curl_setopt($curl_array[$node_key], CURLOPT_FOLLOWLOCATION, true);
            curl_multi_add_handle($mh, $curl_array[$node_key]);
        }

        $active = null;
        do{
            $mrc = curl_multi_exec($mh, $active);
        }while($mrc == CURLM_CALL_MULTI_PERFORM);

        message("i", "Waiting for remote centers' response ...");
        while($active && $mrc == CURLM_OK){
            if(curl_multi_select($mh) != -1){
                do{
                    $mrc = curl_multi_exec($mh, $active);
                }while($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach($nodes as $node){
            if(str_starts_with($node, "-")){continue;}
            if($json_decode){$temp = json_decode(curl_multi_getcontent($curl_array[$node]), true);}
            else{$temp = curl_multi_getcontent($curl_array[$node]);}
            $result[$node] = $temp;
        }

        curl_multi_close($mh);

        return $result;
    }

    function async_connect($nodes, $code, $data, $json_decode = true, $array_type = 0){
        $nodes = base64_encode(json_encode($nodes));
        $code = base64_encode($code);
        $data = base64_encode(json_encode($data));
        $json_decode = base64_encode($json_decode);
        $array_type = base64_encode($array_type);

        pclose(popen('start /B php "E:/project/PTP/PTP Node/src/async_connect.php" {'.$nodes.'} {'.$code.'} {'.$data.'} {'.$json_decode.'} {'.$array_type.'} > NUL &', "r"));
    }

    // Function : donwload
    // Description : Arranging download which split from which node, and connect to them.
    // Algotirhm : Sort splits with the number of resources (from fewest to most), and download the splits form nodes which
    //             has the most splits. This make the fewest splits on the newwork will be request the most time, and those
    //             nodes which has the most splits will provide the most splits.
    function download($seed){
        message("i", "Downloading file ".$seed["FILE_ID"]." ...");
        $total_splits = $seed["SPLITS"];
        $node_count = $split_count = array();
        $remote_data = json_decoder("./temp/temp_".$seed["FILE_ID"].".json");
        $remote_node = array();

        // Counting number of each split and weight of nodes (how many splits it has).
        foreach($remote_data as $node => $splits){
            foreach($splits as $split){
                if(!array_key_exists($split, $split_count)){$split_count[$split] = 0;}
                $split_count[$split]++;
            }
            $node_count[$node] = count($splits);
            if(!in_array($node, $remote_node)){array_push($remote_node, $node);}
        }

        if(count($total_splits) > count($split_count)){message("w", "The file resources are not completed. You may not able to download all the splits.");}
 
        // Sorting splits and nodes.
        asort($split_count);
        arsort($node_count);

        // Making arrangement of splits downloading using the previous algorithm.
        $download_arrangement = array();
        while(count($split_count) != 0){
            $temp_node_count = $node_count;
            foreach($split_count as $split => $_){
                foreach($temp_node_count as $node => $_){
                    if(in_array($split, $remote_data[$node])){
                        array_push($download_arrangement, $node.";".$split);
                        unset($split_count[$split]);
                        unset($temp_node_count[$node]);
                        break;
                    }
                }
            }
        }

        // A sample contents of $download_arrangement :
        // [
        //     "7k3secb7selqspynfrzoujdp2e6ofyl7vavw6efp4rubwmkjxz5ndoyd.onion;73ah5fadac1efc5fbef5b190dc6deeb9a17c857a31edb95bdd8c563f",
        //     "7k3secb7selqspynfrzoujdp2e6ofyl7vavw6efp4rubwmkjxz5ndoyd.onion;9eb6d5aae022b19a18dc1efc5fbf5b190dee9a185731edb95bdd83f2"
        // ]
        // The split been requested will be in the same string with resource and separated by semicolon.

        while(!empty($download_arrangement)){
            // Send PT-0301 request (download request)
            message("i", "Requesting for file : ".$seed["FILE_ID"]." ...");

            // Get password, iv
            $public_key = json_decoder("./conf/config.json")["PUBLIC_KEY"];
            $connect = connect($remote_node, "PT-0309.1", array("PUBLIC_KEY" => $public_key));
            $nodes = json_decoder("./conf/nodes.json");
            $private_key = json_decoder("./conf/config.json")["PRIVATE_KEY"];
            foreach($remote_node as $node){
                $encrypt = $connect[$node]["DATA"];
                $data = json_decode(pb_decrypt($encrypt, "private", $private_key), true);
                $nodes[$node]["PASSWORD"] = $data["PASSWORD"];
                $nodes[$node]["IV"] = $data["IV"];
            }
            json_file_put_contents("./conf/nodes.json", $nodes);

            $connect = connect($download_arrangement, "PT-0301", $seed["FILE_ID"], false, "PT-0301");
            
            $dir = "./seed/".$seed["FILE_ID"]."/";
            mkdir($dir);

            $resend = array();
            foreach($download_arrangement as $node){
                global $resend;
                $temp = explode(";", $node);
                $json = json_decoder("./conf/nodes.json")[$temp[0]];
                $password = $json["PASSWORD"];
                $iv = base64_decode($json["IV"]);
                $split = $temp[1];
                $data = decrypt($connect[$node], $password, $iv);
                if(empty($data)){continue;}

                // Verify split hash
                if(hash("sha256", $data) != explode("-", $split)[1]){$resend = array_shift($download_arrangement[$node]);}
                else{file_put_contents($dir.$split.".ptp", $data);}
            }

            // Combine splits
            $files = scandir($dir);
            foreach($files as $file){
                if($file == "." || $file == ".."){continue;}
                $data = file_get_contents($dir.$file);
                file_put_contents("./download/".$seed["FILE_NAME"], $data, FILE_APPEND | LOCK_EX);
            }

            connect($download_arrangement, "PT-0302", $seed["FILE_ID"], false, "PT-0302");
        }
    }

    function encrypt($data, $password, $iv){
        return base64_encode(openssl_encrypt($data, "AES-256-CBC", $password, OPENSSL_RAW_DATA, $iv));
    }
    
    function decrypt($data, $password, $iv){
        return openssl_decrypt(base64_decode($data), "AES-256-CBC", $password, OPENSSL_RAW_DATA, $iv);
    }

    function pb_encrypt($data, $type, $key){
        $key = base64_decode($key);
        if($type == "public"){openssl_public_encrypt($data, $result, $key);}
        elseif($type == "private"){openssl_private_encrypt($data, $result, $key);}
        return base64_encode($result);
    }

    function pb_decrypt($data, $type, $key){
        $key = base64_decode($key);
        $data = base64_decode($data);
        if($type == "public"){openssl_public_decrypt($data, $result, $key);}
        elseif($type == "private"){openssl_private_decrypt($data, $result, $key);}
        return $result;
    }

    function json_decoder($file, $associative = true){
        return json_decode(file_get_contents($file), $associative);
    }

    function json_file_put_contents($file, $json){
        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | LOCK_EX));
    }

    function message($type, $message){
        $types = array("e" => "Error", "i" => "Info", "w" => "Warning", "f" => "Fatal");
        if(!in_array($type, $types)){$type = "i";}
        error_log("[PTP] -".$types[$type]."- ".$message);
    }
?>