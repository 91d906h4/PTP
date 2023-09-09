Term :
    - Node         : The TorP2P node.
    - Node address : The .onion address of TorP2P node. You can also just say "node".
    - Node list    : A list of TOYP2P nodes.
    - New node     : Nodes in list < 10.
    - Old node     : Nodes in list >= 10.

Files & Directory:
    - torp2p.php               : The main file of node.
    - run.bat                  : File to run node.
    - nodes.json               : Local node list file. The local node must be the first node in this list.

    - /rsc/function.php        : The functions that torp2p.php will use.
    - /rsc/onion_generator.php : The file to generate local node address. (Just used one time in the initialization of node)

    - /conf/config.json        : Local node config file.
    - /conf/torp2p.ini         : Local node config file.

    - README.txt               : The README file.

    torp2p.ini :
        - TOR_PROXY                  : Tor proxy. (Default : "127.0.0.1:9150")
        - HIDDEN_SERVICE_DIR         : The node address file (Can be a relative or absolutive path). (Default : "HiddenServiceDir")

        - CONNECT_NODE_LIMIT         : The limitation of local node to connects in /nodes.json. (Default : 10)
        - CONNECT_TIME_OUT           : The time limitation of connection to a remote node. (Default : 60 (s))
        - ACCEPT_LIST_FROM_CONNECTOR : If onnector is trusted, accept the node list from it or not. 1 means YES, 0 means NO.
                                       (Defualt : 1)

        - TRUST_SCORE                : How many nodes to verify. Local node will verify the one third of the nodes in the list,
                                       if it's greater than TRUST_SCORE, then local node will just verify TRUST_SCORE ondes.
                                       (Defualt : 7)
        - DELETE_UNTRUSTED_NODE      : If delete untrusted nodes from local node list. 1 means YES, 0 means NO. (Defualt : 1)

    config.json :
        - LOCAL_NODE  : Local node address.
        - RUN_KEY     : Verify the node runner is the owner.
        - PUBLIC_KEY  : Local node public key.
        - PRIVATE_KEY : Local node private key.

API :
    - encrypt() :
        Usage     : encrypt(type, key, data);
        Variables :
            type  : message_to_all / message_to_one
            key   : PRIVATE_KEY / PUBLIC_KEY
            data  : unencrypted data
        Return    : encode(unencrypted data)

    - decrypt() :
        Usage     : decrypt(type, key, data);
        Variables  :
            type  : message_from_all / message_from_one
            key   : PRIVATE_KEY / PUBLIC_KEY
            data  : encrypted data
        Return    : unencrypted data