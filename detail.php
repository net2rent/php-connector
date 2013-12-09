<?php

require_once dirname(__FILE__).'/lib/Net2rent/Connector.php';

$connector = new Net2rent\Connector(array(
    'apiBaseUrl' => 'http://hubtesting.n2rent.com',
    'apiUser' => 'portal1',
    'apiPassword' => 'portal1',
    'lg' => 'ca'
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