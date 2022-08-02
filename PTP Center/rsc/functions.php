<?php
    // Include
    require_once("./rsc/onion_generator.php");

    // Setting
    $setting = parse_ini_file("./conf/p2tor.ini");
    set_time_limit(0);

    // Function : initialize
    // Description :  Local node initialize
    function initialize(){
        global $setting;
        $init_file = "./conf/config.json";
        if(!file_exists($init_file)){
            error_log("[P2TOR] -Error- Conf file doesn't exists.");
            die();
        }

        // Add local node info to ./conf/config.json
        $json = json_decoder($init_file);
        if($json["LOCAL_NODE"] == ""){
            $tornado = new Tornado(array('onionSaveDir' => $setting["HIDDEN_SERVICE_DIR"], 'overridePermissions' => true));
            $onioin = $tornado -> generateAddress(1);

            $json["LOCAL_NODE"] = $onioin[0]["address"];

            json_file_put_contents($init_file, $json);
        }
    }

    // Function : json_merge
    // Description : To merge two json file. The previous array has greater weight.
    function json_merge($array, $array1){
        $result = $array;
        foreach($array1 as $key => $value){
            if(array_key_exists($key, $result)  == false){$result[$key] = $array1[$key];}
            else{
                $result[$key] = array_values(array_unique(array_merge($result[$key], $value)));
            }
        }
        return $result;
    }

    // Function : connect
    // Description : Connect to remote node.
    function connect($nodes, $code, $data, $array_type = 0){
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
            if($array_type == 1){
                $temp = explode(";", $node);
                $node = $temp[0];
                $sending["DATA"] = array("REQUEST_SPLIT" => $temp[1]);
            }

            if(str_starts_with($node, "-")){continue;}
            $curl_array[$node] = curl_init(); 
            curl_setopt($curl_array[$node], CURLOPT_URL, $node);
            curl_setopt($curl_array[$node], CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl_array[$node], CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($curl_array[$node], CURLOPT_POSTFIELDS, json_encode($sending));
            curl_setopt($curl_array[$node], CURLOPT_POSTREDIR, 3);
            curl_setopt($curl_array[$node], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_array[$node], CURLOPT_PROXY, $setting["TOR_PROXY"]);
            curl_setopt($curl_array[$node], CURLOPT_PROXYTYPE, 7);
            curl_setopt($curl_array[$node], CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl_array[$node], CURLOPT_TIMEOUT, $setting["CONNECT_TIME_OUT"]);
            curl_setopt($curl_array[$node], CURLOPT_FOLLOWLOCATION, true);
            curl_multi_add_handle($mh, $curl_array[$node]);
        }

        $active = null;
        do{
            $mrc = curl_multi_exec($mh, $active);
        }while($mrc == CURLM_CALL_MULTI_PERFORM);

        error_log("[P2TOR] -Info- Waiting for remote centers' response.");
        while($active && $mrc == CURLM_OK){
            if(curl_multi_select($mh) != -1){
                do{
                    $mrc = curl_multi_exec($mh, $active);
                }while($mrc == CURLM_CALL_MULTI_PERFORM);
            }
        }

        foreach($nodes as $node){
            if(str_starts_with($node, "-")){continue;}
            $result[$node] = curl_multi_getcontent($curl_array[$node]);
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

        pclose(popen('start /B php "E:/project/PTP/PTP Center/src/async_connect.php" {'.$nodes.'} {'.$code.'} {'.$data.'} {'.$json_decode.'} {'.$array_type.'} > NUL &', "r"));
    }

    // Function : update_database
    // Description : Update file between centers.
    function update_database($file_id){
        $file = "./database/".$file_id.".json";
        $centers = json_decoder("./conf/centers.json");
        $new_json = $local_data = json_decoder($file);
        $data = array("FILE_ID" => $file_id, "CONTANTS" => $local_data);

        error_log("[P2TOR] -Info- Updating seed to centers ...");

        async_connect($centers, "PT-0401", $data);

        // $connect = connect($centers, "PT-0401", $data);

        // foreach($centers as $center){
        //     if(str_starts_with($center, "-")){continue;}
        //     if(!$connect[$center]){
        //         error_log("[P2TOR] -Warning- Cannot connect remote center ".$center.".");
        //         continue;
        //     }
        //     $new_json = json_merge($new_json, $connect[$center]["DATA"]);
        // }
        // json_file_put_contents($file, $new_json);
        // error_log("[P2TOR] -Info- Update seed to remote centers completed.");
    }

    function json_decoder($file, $associative = true){
        return json_decode(file_get_contents($file), $associative);
    }

    function json_file_put_contents($file, $json){
        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT | LOCK_EX));
    }
?>