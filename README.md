1. Prerequisite
   1. Installed COMS-Client2 (normally on an other server) - https://github.com/BPMspaceUG/COMS-Client2
   2. URL and Port of the COMS-Client2 installation
   3. Generated machine token to access
   4. Git client installed on machine
2. TODO
   1. "git clone https://github.com/BPMspaceUG/COMS-Client2.git"
   2. copy /inc/api.EXAMPLE_secret.inc.php to /inc/api.secret.inc.php and set: 
        1. $headers[] = 'Cookie: token=[ENTER TOKEN HERE]';
        2. $url = "[ENTER URL HERE]";
        3. define('AUTH_KEY', 'liam2_key');
        4. define('LIAM_URL', 'liam_url');
     
