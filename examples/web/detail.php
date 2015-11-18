<?php

require_once dirname(__FILE__).'/../../vendor/autoload.php';

$config = include(dirname(__FILE__).'/config.php');

$connector = new Net2rent\Connector\Web(array(
    'apiBaseUrl' => $config['apiConnection']['apiBaseUrl'],
    'apiUser' => $config['apiConnection']['apiUser'],
    'apiPassword' => $config['apiConnection']['apiPassword'],
    'companyId' => $config['apiConnection']['companyId'],
    'lg' => $config['language']
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
            die("ERROR: ".$e->getMessage());
    }
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>N2Rent | Connector Web example</title>
        <style type="text/css">
        li { float: left; margin: 5px; clear: both;}
        img { float: left; width: 200px; clear: both; margin: 10px 0px; }
        h2, a {color: #3E78FD;}
    </style>
</head>
<body>
	<div>
            <a href="list.php">Back</a>
            <h2><?php echo $property['name']; ?></h2>
            <img src="<?php echo (count($property['images'])) ? $property['images'][key($property['images'])]['image'] : ''; ?>" alt="<?php echo $property['name']; ?>" title="<?php echo $property['name']; ?>">
            <ul>
                <li>Room number: <?php echo $property['room_number']; ?></li>
                <li>Toilets: <?php echo $property['toilets']; ?></li>
                <li>Description: <?php echo $property['description'][$config['language']]; ?></li>
                <li>Address:
                    <ul>
                        <li><?php echo $property['address']['address']; ?></li>
                        <li><?php echo $property['address']['zone']; ?></li>
                        <li><?php echo $property['address']['urbanization']; ?></li>
                        <li><?php echo $property['address']['zipcode']; ?></li>
                        <li><?php echo $property['address']['city']; ?></li>
                        <li><?php echo $property['address']['province']; ?></li>
                        <li><?php echo $property['address']['country']; ?></li>
                    </ul>
                </li>
                <?php if($property['commercialization_type']=='sale' || $property['commercialization_type']=='rental_sale') { ?>
                <li>Sale price: <?php echo number_format($property['sell_price'],2,',','.'); ?></li>
                <?php } ?>
                
                <li>Equipment:
                    <ul>
                        <?php
                            foreach ($property['equipment'] as $name_eq => $val_eq)
                            {
                                echo "<li>".ucfirst($name_eq).": ".(($val_eq == 1)?"Yes":"No")."</li>";
                            }
                        ?>
                    </ul>
                </li>
            </ul>
	</div>
</body>
</html>
