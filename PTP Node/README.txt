0.Introduction :

    PTP (Peer to Peer, or Peer over Tor to Peer) is a software that allow user to download files
    using P2P technology over Tor. The problem of using PTP is not about anonymity, but about if
    Tor can handle the load. PTP will not make users' IP being logged in centers and the centers
    also will never know the users' real IP. This can make sure users and centers are always be
    anonymous on the network. This is a solution to "Bittorrent over Tor" (take a look at the blog
    of Tor project https://blog.torproject.org/bittorrent-over-tor-isnt-good-idea/).

1.Terms

    Address     : An .onion address to identify a node or center.
    Center      : A server like "tracker" in Bittorrent. The center will trace files, splits, and
                  nodes. Everyone can host has his/her own centers to create a small private file
                  sharing network.
    Node        : Same to "peer". A node on PTP network.
    Remote node : The other nodes.
    Split       : A part of a splited file. It make sure each file will not become too big (as the
                  Tor network cannot handle the load).

2.Files & Directories :

    index.php                 : The main file of node or center.
    run.bat                   : File to run node.

    ./conf/                   : The config files directory.
    ./conf/centers.json       : Local center list. Local node will send update of seed and other
                                informations to all the centers on this list.
    ./conf/config.json        : Local node config file. Including local node address.
    ./conf/nodes.json         : Remote node list. This list include the temporary information like
                                password and iv using to encrypt splits and public key of remote
                                node.
    ./conf/ptp.ini            : Local node config file.

    ./download/               : A directory to save the downloaded files.

    ./rsc/                    : The source directory.
    ./rsc/async_connect.php   : The async connecting program. (Basicly same to the function connect()
                                in ./rsc/functions.php)
    ./rsc/functions.php       : The functions that index.php will use.
    ./rsc/onion_generator.php : The file to generate local node address. (Just used one time in the
                                initialization of node)

    ./seed/                   : A directory to save the uploaded files. Do not modify, remove, or
                                append any file inside this direcoty.

    ./temp/                   : A directory to save the temporary seed information get from center.

    README.txt                : The README file.

3.Settings :

    TOR_PROXY          : The Tor proxy. (Default : 127.0.0.1:9150)
    HIDDEN_SERVICE_DIR : The local node hidden servide dir. This can be relative or absolutive path.
                          (Default : HiddenServiceDir)
    CONNECT_TIME_OUT   : Curl connection time limit. (Default : 120)

4.PTP Protocol :

    Status code :

        PT-01XX : node to center
        PT-02XX : center to node
        PT-03XX : node to node
        PT-04XX : center to center

        PT-0105 :
            Title       : Upload seed information to center.
            Description : When a node want to create a node, it must uplaod the seed to the center,
                          if the center accept this upload, then the node willbe able to create a
                          new seed.

        PT-0106 :
            Title       : Get current seed information from center.
            Description : When a node want to download the files via a seed, it must to connect the
                          center and get a list which tell the node who has which file.

        PT-0109 :
            Title       : Update seed information to center.
            Description : When a node request a split from another node, the requested node will
                          tell the center which node downloaded which split of which file from it.
                          This let the next node can download the splits at same time from differe-
                          nt nodes.

        PT-0205 :
            Title       : Return if seed information updated successfully.
            Description : The code is response of PT-0105. It let the node know if the seed inform-
                          ation is updated successfully.

        PT-0206 :
            Title       : Return current seed information to node.
            Description : The code is response of PT-0106. It returns the current seed information
                          to the node which requested this.

        PT-0301 :
            Title       : Request for split.
            Description : A node requests a split from another node.

        PT-0302 :
            Title       : Send confirm message.
            Description : Send message to resource node when successfully downloaded a split.

        PT-0307* :
            Title       : Send split.
            Description : Send split to node that requests for it. (No message will be send)
                          (All splits will be encrypted in AES-256-CBC)

        PT-0308 [Deprecation] :
            Title       : Reject download requst.
            Description : A node (node A) is downloading a split form another node (node B), if
                          node A send another request to node B to ask for another split, then node
                          B will send PT-0308 to reject the download request. (Each node can only
                          download one split from a node at the same time)


        PT-0309.1 :
            Title       : Request for password and iv.
            Description : When a node want to download a file, it will request for password and iv
                          pair from remote node, and send the local public key to remote to encrypt
                          the password and iv pair.

        PT-0309.2* :
            Title       : Return password and iv.
            Description : The code is response of PT-0309.1. It return the temporary password and iv
                          using to encrypt splits. (No message will be send)
        PT-0401 :
            Title       : Share seed information.
            Description : If the seed information on a center is updated, then the center will send
                          the update to other centers, and also accept the seed information from
                          them.