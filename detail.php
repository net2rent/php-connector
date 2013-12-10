<?php

require_once dirname(__FILE__).'/lib/Net2rent/Connector.php';

$config = parse_ini_file("config.ini", true);

$connector = new Net2rent\Connector(array(
    'apiBaseUrl' => $config['api_connection']['apiBaseUrl'],
    'apiUser' => $config['api_connection']['apiUser'],
    'apiPassword' => $config['api_connection']['apiPassword'],
    'lg' => $config['language']['lg']
));

try {
    $property = $connector->getProperty($_REQUEST['id']);
}
catch(Exception $e) {
    switch($e->getCode()) {
        case 401:
            print("Forbidden access <br/>\n");
            break;
        default:
            print("Error");
    }
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>N2Rent | Connector example</title>
</head>
<body>
	<div>
		<?php var_dump($property); ?>
	</div>
</body>
</html>