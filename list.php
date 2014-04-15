<?php

require_once dirname(__FILE__).'/vendor/autoload.php';

$config = parse_ini_file("config.ini", true);

$connector = new Net2rent\Connector(array(
    'apiBaseUrl' => $config['api_connection']['apiBaseUrl'],
    'apiUser' => $config['api_connection']['apiUser'],
    'apiPassword' => $config['api_connection']['apiPassword'],
    'lg' => $config['language']['lg']
));

$page_size = $config['portal']['elementsperpage'];
$page_number = (isset($_REQUEST['page'])) ? $_REQUEST['page'] : 1;

$properties = array();
try {
    $options = array();
    $options['page_number'] = $page_number;
    $options['page_size'] = $page_size;

    if(isset($_REQUEST['checkin']) && $_REQUEST['checkin']) {
        $options['checkin'] = $_REQUEST['checkin'];
        $options['checkout'] = (isset($_REQUEST['checkout'])) ? $_REQUEST['checkout'] : null;;
    }
    $options['city'] = (isset($_GET['city']))?$_GET['city']:null;

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

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>N2Rent | Connector example</title>
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
                            <p><?php echo $property['description'][$config['language']['lg']]; ?></p>
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
