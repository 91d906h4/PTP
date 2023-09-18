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
    $setting = parse_ini_file("./conf/torp2p.ini");

    // Initializing
    initialize();

    // Verify node runner
    // verify_runner();

    // Return local nodes list
    if($_SERVER['REQUEST_METHOD'] == "POST"){
        // Get remote node list
        $remote_data = json_decoder("php://input");
        error_log("[TORP2P] -Info- Remote node ".$remote_data["CONNECTOR"]." connecting ...");

        $connection_type = $remote_data["CONNECTION_CODE"];
        if($connection_type == "CN-5963"){
            $local_node_list = json_decoder("./nodes.json");
            $remote_node_list = $remote_data["DATA"];
    
            // Send local node lsit to remote
            error_log("[TORP2P] -Info- Response remote connection.");
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode($local_node_list);
    
            // Verify remote node list
            if($setting["ACCEPT_LIST_FROM_CONNECTOR"] != 1){die();}
            error_log("[TORP2P] -Info- Verifying remote node ".$remote_data["CONNECTOR"]." ...");
            unset($remote_node_list[$remote_data["CONNECTOR"]]); // It will not verify the request sender node.
            $verify = verify_node(array_slice($remote_node_list, 1));
            if($verify == 1){
                $new_list = node_list_merge($local_node_list, $remote_node_list);
                json_file_put_contents("./nodes.json", $new_list);
                error_log("[TORP2P] -Info- Accept node list from remote node ".$remote_data["CONNECTOR"].".");
            }
            else{
                error_log("[TORP2P] -Warning- Remote node ".$remote_data["CONNECTOR"]." is not trusted.");
            }
    
            die();
        }
        elseif($connection_type == "CN-1965"){
            $local_verify_key = json_decoder("./conf/config.json");
            $remote_verify_key = $remote_data["DATA"];
            
            error_log($remote_verify_key["STATUS_CODE"]);
            $remote_verify_key = $remote_verify_key["DATA"];
    
            if($local_verify_key["PUBLIC_KEY"] == $remote_verify_key["PUBLIC_KEY"]){$result = "TRUE";}
            else{$result = "FLASE";}
    
            error_log("[TORP2P] -Info- Response remote connection.");
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode(array("VERIFY" => $result));
    
            die();
        }
    }

    // Connect to remote nodes
    $nodes = json_decoder("./nodes.json");
    // $nodes = array_rand($nodes, 10);
    // connect($nodes);
?>