<?php
    // Setting
    $setting = parse_ini_file("./conf/ptp.ini");
    set_time_limit(0);

    // Variables
    $nodes = json_decode(base64_decode($argv[1]), true);
    $code = base64_decode($argv[2]);
    $data = json_decode(base64_decode($argv[3]), true);
    $json_decode = base64_decode($argv[4]);
    $array_type = base64_decode($argv[5]);

    connect($nodes, $code, $data, $json_decode, $array_type);

    function connect($nodes, $code, $data, $json_decode = true, $array_type = 0){
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
            if($json_decode){$temp = json_decode(curl_multi_getcontent($curl_array[$node]), true);}
            else{$temp = curl_multi_getcontent($curl_array[$node]);}
            $result[$node] = $temp;
        }

        curl_multi_close($mh);

        return $result;
    }

    function json_decoder($file, $associative = true){
        return json_decode(file_get_contents($file), $associative);
    }
?>