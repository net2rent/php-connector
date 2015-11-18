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

$page_size = $config['itemsperpage'];
$page_number = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : 1;

$properties = array();
try {
    $options = array();
    $options['page_number'] = $page_number; // number of selected page
    $options['page_size'] = $page_size; // items per page
    $options['max_w'] = 200; // max width size for images
    $options['max_h'] = 300; // max height size for images

    if(isset($_REQUEST['checkin']) && $_REQUEST['checkin']) {
        $options['checkin'] = $_REQUEST['checkin'];
        $options['checkout'] = (isset($_REQUEST['checkout'])) ? $_REQUEST['checkout'] : null;
    }
    $options['name'] = (isset($_REQUEST['name']))?$_REQUEST['name']:null; // filter by name
    $options['city'] = (isset($_REQUEST['city']))?$_REQUEST['city']:null; // filter by city
    $options['sell_price_min'] = (isset($_REQUEST['sell_price_min']))?$_REQUEST['sell_price_min']:null; // filter by city
    $options['sell_price_max'] = (isset($_REQUEST['sell_price_max']))?$_REQUEST['sell_price_max']:null; // filter by city
    $options['commercialization_type'] = (isset($_REQUEST['commercialization_type']))?$_REQUEST['commercialization_type']:null; // filter by commercialization type
    $options['building_type'] = (isset($_REQUEST['building_type']))?$_REQUEST['building_type']:null; // filter by building type
    $options['highlighted'] = (isset($_REQUEST['highlighted']))?1:null; // filter by name
    
    $properties = $connector->getProperties($options);
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

$cities = array();
try
{
    $cities = $connector->getCities();
}
catch(Exception $e)
{
    switch($e->getCode()) {
        case 401:
            print("Forbidden access <br/>\n");
            break;
        default:
            print("Error");
    }
}

$building_types = array();
try
{
    $building_types = $connector->getBuildingTypes();
}
catch(Exception $e)
{
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
    <title>N2Rent | Connector Web example</title>
    <style type="text/css">
        fieldset { float: left; border: none; padding: 5px; }
        form { background-color: #CCC; float: left; padding: 5px; }
        a { color: #3E78FD; }
        div { float: left; clear: both; }
        li { float: left; list-style-type: none; margin: 5px; }
        img { float: left; width: 200px; }
    </style>
</head>
<body>
    <div>
        <h2>Property list</h2>
        <div>
            <form action="" method="GET" name="searchForm" id="searchForm">
                <fieldset>
                    <input type="date" name="checkin" placeholder="Checkin"/>
                </fieldset>
                <fieldset>
                    <input type="date" name="checkout" placeholder="Checkout"/>
                </fieldset>
                <fieldset>
                    <input type="text" name="name" placeholder="name" value="<?php echo (isset($_REQUEST['name']) && $_REQUEST['name'])?$_REQUEST['name'] : ''; ?>" />
                </fieldset>
                <fieldset>
                    City
                    <select name="city">
                        <option value="">Any</option>
                        <?php
                            foreach($cities as $city)
                            {
                                printf('<option value="%1$s" %2$s>%1$s</option>', $city, (isset($_GET['city']) && $_GET['city'] == $city) ? 'selected="selected"' : '');
                            }
                        ?>
                    </select>
                </fieldset>
                
                <fieldset>
                    Commercialization type
                    <select name="commercialization_type">
                        <option value="">Any</option>
                        <?php
                            $commercialization_types=array('rental','sale','property_management','rental_sale','rental_property_management');
                            foreach($commercialization_types as $commercialization_type)
                            {
                                printf('<option value="%1$s" %2$s>%1$s</option>', $commercialization_type, (isset($_GET['commercialization_type']) && $_GET['commercialization_type'] == $commercialization_type) ? 'selected="selected"' : '');
                            }
                        ?>
                    </select>
                </fieldset>
                
                <fieldset>
                    Building type
                    <select name="building_type">
                        <option value="">Any</option>
                        <?php
                            foreach($building_types as $building_type)
                            {
                                printf('<option value="%1$s" %2$s>%1$s</option>', $building_type['option_name'], (isset($_GET['building_type']) && $_GET['building_type'] == $building_type['option_name']) ? 'selected="selected"' : '');
                            }
                        ?>
                    </select>
                </fieldset>
                
                <fieldset>
                    <input type="text" name="sell_price_min" placeholder="sell_price_min" value="<?php echo (isset($_REQUEST['sell_price_min']) && $_REQUEST['sell_price_min'])?$_REQUEST['sell_price_min'] : ''; ?>" />
                </fieldset>
                
                <fieldset>
                    <input type="text" name="sell_price_max" placeholder="sell_price_max" value="<?php echo (isset($_REQUEST['sell_price_max']) && $_REQUEST['sell_price_max'])?$_REQUEST['sell_price_max'] : ''; ?>" />
                </fieldset>
                
                <fieldset>
                    Highlighted
                    <input type="checkbox" name="highlighted" value="1" <?php echo (isset($_REQUEST['highlighted']) && $_REQUEST['highlighted'])? 'checked="checked"' : ''; ?> />
                </fieldset>
                
                <!--<select name="country">
                    <option value="">Any</option>
                    <?php
                        /*foreach($connector->getCountries() as $country)
                        {
                            foreach($country as $country_key => $country_name)
                            {
                                echo "<option value=\"".$country_key."\">".$country_name."</option>";
                            }
                        }*/
                    ?>
                </select>-->
                <fieldset>
                    <input type="submit" value="Search"/>
                </fieldset>
            </form>
        </div>
        <div>
            <?php
                $totalPages = ceil((int)$properties['total']/(int)$page_size);
                printf('<p>Page %d of %d</p>', $page_number, $totalPages);
            ?>
            <ul>
                <?php
                    for($i=0;$i<$totalPages;$i++)
                    {
                        printf('<li><a class="%2$s" href="?page=%1$d">%1$d</a></li>', $i+1, ($i+1 == $page_number) ? 'selected' : '');
                    }
                ?>
            </ul>
        </div>
        <div>
            <p>Properties (Total properties: <?php echo $properties['total']; ?>)</p>
            <ul>
                <?php
                foreach($properties['items'] as $property) {
                    ?>
                        <li style="clear: both;">
                            <a href="detail.php?id=<?php echo $property['id']; ?>"><?php echo $property['name']; ?></a>
                            <p><?php echo $property['description'][$config['language']]; ?></p>
                            <img src="<?php echo $property['image']; ?>" alt="<?php echo $property['name']; ?>" title="<?php echo $property['name']; ?>">
                        </li>
                    <?php
                }
                ?>
            </ul>
        </div>
    </div>
</body>
</html>
