<?php

/**
 * Takes the requests of the system wanting to update its DNS
 * and processes it then passes it onto my nameservers
 * @author Bruce Taylor <bruce@brucedev.com>
 * @license https://www.mozilla.org/MPL/2.0/ Mozilla Public License
 */

class dnsApi
{
	public static function run()
	{
		//Output as JSON
		header('Content-Type: application/json; charset=UTF-8');

		if(empty($_GET['command']))
		{
			throw new Exception('Command parameter required');
		}
		$command = $_GET['command'];

		//Let's do the actual command fun times
		switch ($command)
		{
			case 'update':
				$reply = self::updateDnsInfo();
				break;
			case 'info':
				$reply = self::showDnsInfo();
				break;
			default:
				throw new Exception("Unknown command: $command");
		}
		
		//Whilst unlikely, let's be sure we have a way to handle an empty reply
		if(!isset($reply))
		{
			throw new Exception('$reply not set');
		}
		
		echo json_encode($reply);
	}

	/**
	 * Takes the Secret Key of the GET Request
	 * and ensures it matches the one specified
	 * @return boolean 1 on valid 0 on invalid
	*/

	private static function checkSecretKey()
	{
		if(isset($_GET['key']) && $_GET['key'] == SECRET_KEY)
		{
			return 1;
		}
		else
		{
			return 0;
		}
	}
	
	private static function updateDnsInfo()
	{
		if(self::checkSecretKey() === 0)
		{
			return array('state' => 'Error', 'detail' => 'Invalid or Missing Secret Key' );
		}

		$userIp = $_SERVER['REMOTE_ADDR'];

		if($userIp == gethostbyname(DNS_RECORD))
		{
			return array('state' => 'Unchanged', 'detail' => DNS_RECORD . " is already set to $userIp");
		}

		//Crafting the actual file that nsupdate will run
		$file  = "server ". DNS_SERVER . "\n";
		$file .= "debug yes\n";
		$file .= "zone " . DNS_ZONE . "\n";
		$file .= "update delete " . DNS_RECORD . ". A\n";
		$file .= "update add " . DNS_RECORD ." 300 A $userIp\n";
		$file .= "show\n";
		$file .= "send";

		file_put_contents('/tmp/update.txt', $file);

		$output = shell_exec('nsupdate -k ' . DNS_KEY_PATH . '/' . DNS_KEY_NAME . '.private -v /tmp/update.txt');
		unlink('/tmp/update.txt');

		if(strpos($output, 'SERVFAIL') !== false)
		{
			return array('state' => 'Error', 'detail' => 'DNS Server gave invalid response');
		}
		else
		{
			return array('state' => 'Success', 'detail' => DNS_RECORD . " successfully updated to $userIp");
		}
	}

}
