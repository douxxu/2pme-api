<?php

#Cloudflare | Your domain must be parked on cloudflare
$cloudflare_api_url = "https://api.cloudflare.com/client/v4/zones/INSERT_ZONE_ID_HERE/dns_records"; #Your cloudflare api url
$cloudflare_api_key = "INSERT_API_KEY_HERE"; #Your cloudflare API token (Needs DNS management access)
$cloudflare_email = "INSERT_EMAIL_HERE"; #Your email related to your cloudflare domain
$zoneId = "INSERT_ZONE_ID_HERE"; #Your domain cloudflare zone id


#Database | Needs to supports mysqli requests
$host = 'IP:PORT'; #Your database host 
$dbname = 'NAME'; #Your database name
$username = 'USERNAME'; #Your database username
$password = 'PWD'; #Your database password
?>
