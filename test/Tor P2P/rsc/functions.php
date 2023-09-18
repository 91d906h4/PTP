<?php
    // Include
    require_once("./rsc/onion_generator.php");

    // Setting
    $setting = parse_ini_file("./conf/torp2p.ini");
    set_time_limit(0);

    // Function : initialize
    // Description :  Local node initialize
    function initialize(){
        global $setting;
        $init_file = "./conf/config.json";
        if(file_exists($init_file)){
            $json = json_decoder($init_file);
            // Add local node info to ./conf/config.json
            if($json["LOCAL_NODE"] == "" || $json["PUBLIC_KEY"] == "" || $json["PUBLIC_KEY"] == "" || $json["RUN_KEY"] == ""){
                $tornado = new Tornado(array('onionSaveDir' => $setting["HIDDEN_SERVICE_DIR"], 'overridePermissions' => true));
                $onioin = $tornado -> generateAddress(1);

                $RSA = keypair_generator();
                $json["LOCAL_NODE"] = $onioin[0]["address"];
                $json["RUN_KEY"] = hash("sha512", substr(str_shuffle("01234567890123456789aAbBcCdDeEfFgGhHiIjJkKlLmMnNoOpPqQrRsStTuUvVwWxXyYzZ"), 64));
                $json["PUBLIC_KEY"] = $RSA["PUBLIC_KEY"];
                $json["PUBLIC_KEY"] = $RSA["PUBLIC_KEY"];

                json_file_put_contents($init_file, $json);
            }

            // Add local node info to ./nodes.json
            $local = json_decoder($init_file);
            $json = json_decoder("./nodes.json");
            if(!isset($json[$local["LOCAL_NODE"]])){$json[$local["LOCAL_NODE"]] = array("Public_key" => $local["PUBLIC_KEY"], "Resource" => [""]);}
            json_file_put_contents("./nodes.json", $json);
        }
        else{
            error_log("[TORP2P] -Error- Conf file doesn't exists.");
            die();
        }
    }

    // Function : verify_runner
    // Description : To verify the runner if the owner (using RUN_KEY in ./conf/config.json).
    function verify_runner(){
        $run_key = json_decoder("./conf/config.json")["RUN_KEY"];
        if((isset($_SESSION["r"]) && $_SESSION["r"] == $run_key) || (isset($_GET["r"]) & $_GET["r"] == $run_key)){
            error_log("[TORP2P] -Info- Run key successly verified.");
            $_SESSION["r"] = $run_key;
        }
        else{die("NO PERMISION.");}
    }

    // Function : node_list_merge
    // Description : To merge the node list get from remote. The previous array has greater weight.
    function node_list_merge($array, $array1){
        $result = $array;
        foreach($array1 as $key => $value){
            if(array_key_exists($key, $result)  == false){$result[$key] = $array1[$key];}
            else{
                $result[$key]["Resource"] = array_values(array_unique(array_merge($result[$key]["Resource"], $value["Resource"])));
            }
        }
        return $result;
    }

    // Function : connect_node
    // Description : Connect to remote node and get response.
    function connect_node($node, $method, $code, $data){
        global $setting;
        $local_node_info = json_decoder("./conf/config.json");

        $sending = array(
            "CONNECTOR" => $local_node_info["LOCAL_NODE"],
            "CONNECTION_CODE" => $code,
            "DATA" => $data
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $node);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($sending));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTREDIR, 3);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_PROXY, $setting["TOR_PROXY"]);
        curl_setopt($curl, CURLOPT_PROXYTYPE, 7);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0); 
        curl_setopt($curl, CURLOPT_TIMEOUT, $setting["CONNECT_TIME_OUT"]);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        $result = curl_exec($curl);
        curl_close($curl);

        return array("result" => $result, "error" => curl_error($curl));
    }

    // Function : verify_node
    // Description : Verify remote node with remote_node_list.
    function verify_node($remote_node_list){
        global $setting;
        // If remote is a new node (nodes in list < 10), just send local node list to remote.
        $len = count($remote_node_list);
        $process_a = $len;
        $trust_score = min($setting["TRUST_SCORE"], 2 * intval($len / 3));
        $process_b = 0;
        if($process_a == 0){error_log("[TORP2P] -Info- Remote node ha sno list to verify."); return 1;}
        $remote_node_list_temp = array_rand($remote_node_list, $len);
        foreach($remote_node_list_temp as $temp){
            error_log("[TORP2P] -Info- Verifying... ".strval(round(intval($process_b) / intval($process_a) * 100))."%");
            $process_b++;
            if(json_decoder("./conf/config.json")["LOCAL_NODE"] == $temp) continue;
    
            $curl = connect_node("http://".$temp."/", "PUT", "CN-1965", array("PUBLIC_KEY" => $remote_node_list[$temp]["Public_key"]));
            if($curl["result"]){$result = json_decode($curl["result"], true)["VERIFY"];}
            else{$result = "UNKNOW";}

            if($result == "UNKNOW") continue;
            elseif($result == "TRUE") $trust_score--;
            elseif($result == "FALSE" || $trust_score == 0) break;
        }
        error_log("[TORP2P] -Info- Verifying... 100%");

        return intval($trust_score == 0);
    }

    // Function : connect
    // Description : Send request to get node list from remote
    function connect($nodes){
        global $setting;
        $node_list_to_remote = json_decoder("./nodes.json");
        $connect_limit = $setting["CONNECT_NODE_LIMIT"];
        $disconnect_list = array();
        foreach($nodes as $node => $info){
        // foreach($nodes as $node){
            if($connect_limit == 0) break;
            if($_SERVER["HTTP_HOST"] == $node) continue;

            error_log("[TORP2P] -Info- Connecting to node ".$node." ...");

            $curl = connect_node("http://".$node."/", "POST", "CN-5963", $node_list_to_remote);
            $result = $curl["result"];
            $curl_error = $curl["error"];

            if($result){
                error_log("[TORP2P] -Info- Connection successed.");
                $local_node_list = json_decoder("./nodes.json");
                $remote_node_list = json_decode($result, true);

                // Verify remote node list
                error_log("[TORP2P] -Info- Verifying remote node ".$node." ...");
                $verify = verify_node($remote_node_list);
                $new_list = $local_node_list;
                if($verify == 1){
                    $new_list = node_list_merge($local_node_list, $remote_node_list);
                    error_log("[TORP2P] -Info- Accept node list from remote node ".$node.".");
                }
                else{
                    error_log("[TORP2P] -Warning- Remote node ".$node." is not trusted.");
                    if($setting["DELETE_UNTRUSTED_NODE"] == 1){unset($new_list[$node]);}
                }
                json_file_put_contents("./nodes.json", ($new_list));
            }
            else{
                error_log("[TORP2P] -Info- Connection failed. Error message : ".($curl_error ?: "No error message."));
                if(str_ends_with($curl_error, "(246)")){array_push($disconnect_list, $node);} // Delete invalide address from node list.
            }
            $connect_limit--;
        }
        $local_node_list = json_decoder("./nodes.json");
        foreach($disconnect_list as $node){unset($local_node_list[$node]);}
        json_file_put_contents("./nodes.json", $local_node_list);
    }

    function keypair_generator(){
        $config = array(
            "digest_alg" => "sha512",
            "private_key_bits" => 1024,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        $RSA = openssl_pkey_new($config);
        openssl_pkey_export($RSA, $private_key);
        $public_key = openssl_pkey_get_details($RSA)["key"];

        return array("PUBLIC_KEY" => encode($public_key), "PRIVATE_KEY" => encode($private_key));
    }

    function json_decoder($file, $associative = true){
        return json_decode(file_get_contents($file), $associative);
    }

    function json_file_put_contents($file, $json){
        file_put_contents($file, json_encode($json, JSON_PRETTY_PRINT));
    }

    function encode($string){
        return bin2hex(gzdeflate($string, 9));
    }

    function decode($string){
        return gzinflate(hex2bin($string));
    }

    function encrypt($type, $key, $data){
        if($type == "message_to_one") openssl_public_encrypt($data, $encrypted, decode($key));
        elseif($type == "message_to_all") openssl_private_encrypt($data, $encrypted, decode($key));
        return encode($encrypted);
    }

    function decrypt($type, $key, $data){
        if($type == "message_from_one") openssl_public_decrypt(decode($data), $decrypted, decode($key));
        elseif($type == "message_form_all") openssl_private_decrypt(decode($data), $decrypted, decode($key));
        return $decrypted;
    }
?>