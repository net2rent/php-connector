<?php
namespace Net2rent\Connector;

if (!function_exists('curl_init')) {
    throw new \Exception('Net2rent connector needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new \Exception('Net2rent connector needs the JSON PHP extension.');
}

abstract class AbstractConnector
{
    const VERSION = '0.2.1';

    protected $apiBaseUrl;
    protected $apiUser;
    protected $apiPassword;
    protected $lg;

    protected $companyId;

    public static $CURL_OPTS = array(
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 240,
        CURLOPT_USERAGENT => 'net2rent-connector-php-0.2.1',
    );

    /**
     * [__construct description]
     *
     * @param string $options['apiBaseUrl'] Api Url base
     * @param string $options['apiUser'] Api User
     * @param string $options['apiPassword'] Api Password
     * @param string $options['lg'] Language
     */
    public function __construct(array $options = array())
    {
        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function getCountries()
    {
        return $this->api('/globals/countries?lg=' . $this->lg);
    }

    public function getBuildingTypes()
    {
        return $this->api('/enums/values/type_name/property_type');
    }

    /**
     * Get cities
     * @param  string $options['commercialization_type'] Valid values: [rental, sale, rental_sale, property_management, rental_property_management], can be multiple separed by , (comma)
     * @param
     * @return array
     */
    public function getCities(array $options = array())
    {
        $endPoint = $this->getEndPoint('cities');

        $params = array();
        if(isset($options['commercialization_type'])) {
            $params['commercialization_type'] = $options['commercialization_type'];
        }
        return $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
    }

    protected function getEndPoint($type, $params = null)
    {
        if (!isset($this->_endPoints[$type])) {
            throw new \Exception(sprintf('EndPoint "%s" not found.', $type));
        }
        $replaceVars = array(
            '{{portal}}' => $this->apiUser,
            '{{company}}' => $this->companyId,
        );
        $endPoint = str_replace(array_keys($replaceVars) , array_values($replaceVars) , $this->_endPoints[$type]);
        if (count($params) > 0) {
            array_unshift($params, $endPoint);
            $endPoint = call_user_func_array('sprintf', $params);
        }
        return $endPoint;
    }

    /**
     * Get properties
     *
     * @param  integer  $options['page_number'] Page number
     * @param  integer  $options['page_size'] Page size
     * @param  date  $options['checkin'] Format YYYY-MM-DD. Optional
     * @param  date  $options['checkout'] Format YYYY-MM-DD. Optional
     * @param  string $options['city'] Filter by city name
     * @param  string $options['external_ref'] Filter by external_ref
     * @param  integer $options['company_id'] Filter by company_id
     * @param  string $options['commercialization_type'] Valid values: [rental, sale, rental_sale, property_management, rental_property_management], can be multiple separed by , (comma)
     * @param  string $options['rental_type'] Valid values: [turistic, season, residential, any], can be multiple separed by , (comma)
     * @param  integer  $options['max_w'] Max width of image
     * @param  integer  $options['max_h'] Max height of image
     * @param  integer  $options['quality'] JPEG quality percent of image
     * @param  integer  $options['web'] 1/0 if 1, show available only if web_visible is 1
     * @param  string   $options['orderby'] Order by field
     * @param  integer  $options['orderdesc'] 1/0 if 1, order DESC, else order ASC
     * @param  boolean  $options['simple_response'] To get few fields. Only available from Portal connector
     * @param  string $options['pool_type'] Valid values: [no_pool, communal, private]
     * @param  float $options['sell_price_min'] Minimum sell price
     * @param  float $options['sell_price_max'] Maximum sell price
     * @return array  (items|total)
     */
    public function getProperties(array $options = array())
    {
        $endPoint = $this->getEndPoint((isset($options['checkin'])) ? 'properties_availables' : 'properties');

        $params = array();
        if (isset($options['page_number'])) {
            $limit = (isset($options['page_size'])) ? $options['page_size'] : 10;
            $params['limit'] = $limit;
            $params['offset'] = ($options['page_number'] - 1) * $limit;
        }
        if (isset($options['checkin']) && isset($options['checkout'])) {
            $params['date_in'] = $this->checkDateFormat($options['checkin']);
            $params['date_out'] = $this->checkDateFormat($options['checkout']);
        }
        if (isset($options['search'])) {
            $params['search'] = $options['search'];
        }
        if (isset($options['city'])) {
            $params['city'] = $options['city'];
        }
        if (isset($options['external_ref'])) {
            $params['external_ref'] = $options['external_ref'];
        }
        if (isset($options['company_id'])) {
            $params['company_id'] = $options['company_id'];
        }
        if (isset($options['name'])) {
            $params['name'] = $options['name'];
        }
        if (isset($options['commercialization_type'])) {
            $params['commercialization_type'] = $options['commercialization_type'];
        }
        if (isset($options['rent_type'])) {
            $params['rent_type'] = $options['rent_type'];
        }
        if (isset($options['building_type'])) {
            $params['building_type'] = $options['building_type'];
        }
        if (isset($options['highlighted'])) {
            $params['highlighted'] = $options['highlighted'];
        }
        if (isset($options['sell_price_min'])) {
            $params['sell_price_min'] = $options['sell_price_min'];
        }
        if (isset($options['sell_price_max'])) {
            $params['sell_price_max'] = $options['sell_price_max'];
        }
        if (isset($options['min_capacity'])) {
            $params['min_capacity'] = $options['min_capacity'];
        }
        if (isset($options['max_capacity'])) {
            $params['max_capacity'] = $options['max_capacity'];
        }
        $params['max_w']=4000;
        if (isset($options['max_w'])) {
            $params['max_w'] = $options['max_w'];
        }
        $params['max_h']=3000;
        if (isset($options['max_h'])) {
            $params['max_h'] = $options['max_h'];
        }
        $params['quality']=80;
        if (isset($options['quality'])) {
            $params['quality'] = $options['quality'];
        }
        if (isset($options['web'])) {
            $params['web'] = $options['web'];
        }
        if (isset($options['orderby'])) {
            $params['orderby'] = $options['orderby'];
        }
        if (isset($options['orderdesc'])) {
            $params['orderdesc'] = $options['orderdesc'];
        }
        if (isset($options['simple_response'])) {
            $params['simple_response'] = ($options['simple_response']) ? '1' : '0';
        }
        if (isset($options['pool_type'])) {
            $params['pool_type'] = $options['pool_type'];
        }
        if (isset($options['sell_price_min'])) {
            $params['sell_price_min'] = $options['sell_price_min'];
        }
        if (isset($options['sell_price_max'])) {
            $params['sell_price_max'] = $options['sell_price_max'];
        }
        $params['lg'] = $this->lg;

        $typologies = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $typologiesTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

        $properties = array();
        foreach ($typologies as $typology) {
            if(isset($options['simple_response']) && $options['simple_response']) {
                $properties[] = array(
                    'id' => $typology['id'],
                    'name' => $typology['name'],
                    'ref' => $typology['property_ref_property_string'],
                    'price_from' => $typology['price_from']
                );
            }
            else {
                $equipment_array = $this->api("/typologies/" . $typology['id'] . "/equipment");
                $equipments = array();
                $not_equipments = array(
                    'id',
                    'typology_id',
                    'creation_date',
                    'creation_usr',
                    'edition_date',
                    'edition_usr'
                );
                foreach ($equipment_array as $equipment_name => $equipment_value) {
                    if (strpos($equipment_name, '_model') == false && in_array($equipment_name, $not_equipments, true) == false) {
                        $equipments[$equipment_name] = $equipment_value;
                    }
                }

                $properties[] = array(
                    'id' => $typology['id'],
                    'name' => $typology['name'],
                    'ref' => $typology['property_ref_property_string'],
                    'generalitat_reference' => $typology['property_generalitat_reference'],
                    'capacity' => $typology['capacity'],
                    'building_type' => $typology['building_type'],
                    'commercialization_type' => $typology['commercialization_type'],
                    'rent_type' => $typology['rent_type'],
                    'description' => array(
                        'es' => strip_tags($typology['description_es']) ,
                        'ca' => strip_tags($typology['description_ca']) ,
                        'en' => strip_tags($typology['description_en']) ,
                        'fr' => strip_tags($typology['description_fr']) ,
                        'de' => strip_tags($typology['description_de']) ,
                        'nl' => strip_tags($typology['description_nl']) ,
                        'it' => strip_tags($typology['description_it']) ,
                        'ru' => strip_tags($typology['description_ru'])
                    ) ,
                    'address' => array(
                        'address' => $typology['building_address'],
                        'urbanization' => $typology['building_urbanization'],
                        'zipcode' => $typology['building_cp'],
                        'zone' => $typology['building_zone'],
                        'country' => $typology['building_country_iso'],
                        'province' => $typology['building_province'],
                        'city' => $typology['building_city'],
                        'latitude' => $typology['building_lat'],
                        'longitude' => $typology['building_long']
                    ) ,
                    'equipment' => $equipments,
                    'average_evaluation' => $typology['average_evaluation'],
                    'room_number' => $typology['room_number'],
                    'toilets' => $typology['toilets'],
                    'bathroom_tub'=> $typology['property_bathroom_tub'],
                    'bathroom_shower'=> $typology['property_bathroom_shower'],
                    'total_toilets' => $typology['toilets']+$typology['property_bathroom_tub']+$typology['property_bathroom_shower'],
                    'price' => $typology['rent_price'],
                    'price_offer' => $typology['rent_price_offer'],
                    'sell_price' => $typology['sell_price'],
                    'sell_price_offer' => $typology['sell_price_offer'],
                    'price_from' => isset($typology['price_from']) ? $typology['price_from'] : 0,
                    'totalprice' => isset($typology['totalprice']) ? $typology['totalprice'] : 0,
                    'finalprice' => isset($typology['finalprice']) ? $typology['finalprice'] : 0,
                    'online_reservation' => $typology['property_online_reservation'],
					'pool_type'=> $typology['pool_type'],
                    'property_meters'=> $typology['property_property_meters'],

                    'image' => (isset($typology['image_id'])) ? sprintf('%s/typologies/%s/images/%s/image.jpg?max_w=%s&max_h=%s&quality=%s',
                            $this->apiBaseUrl,
                            $typology['id'],
                            $typology['image_id'],
                            $params['max_w'],
                            $params['max_h'],
                            $params['quality']
                            )
                    : null,
                    'creation_date' => isset($typology['creation_date']) ? $typology['creation_date'] : null,
                    'edition_date' => isset($typology['edition_date']) ? $typology['edition_date'] : null
                );
            }

        }

        return array(
            'total' => $typologiesTotal['size'],
            'items' => $properties
        );
    }

    /**
     * Get a property
     *
     * @param  string  $property_id
     * @param  date  $options['checkin'] Format YYYY-MM-DD. Optional
     * @param  date  $options['checkout'] Format YYYY-MM-DD. Optional
     * @param  integer  $options['max_w'] Max width of image
     * @param  integer  $options['max_h'] Max height of image
     * @param  integer  $options['quality'] JPEG quality percent of image
     * @param  integer  $options['web'] 1/0 if 1, show available only if web_visible is 1
     * @return array  (items)
     */
    public function getProperty($property_id, array $options = array())
    {
        $endPoint = $this->getEndPoint((isset($options['checkin'])) ? 'property_available' : 'property', array(
            $property_id
        ));

        $params = array();
        if (isset($options['checkin']) && isset($options['checkout'])) {
            $params['date_in'] = $this->checkDateFormat($options['checkin']);
            $params['date_out'] = $this->checkDateFormat($options['checkout']);
        }
        $params['max_w']=4000;
        if (isset($options['max_w'])) {
            $params['max_w'] = $options['max_w'];
        }
        $params['max_h']=3000;
        if (isset($options['max_h'])) {
            $params['max_h'] = $options['max_h'];
        }
        $params['quality']=80;
        if (isset($options['quality'])) {
            $params['quality'] = $options['quality'];
        }
        if (isset($options['web'])) {
            $params['web'] = $options['web'];
        }
        $params['lg'] = $this->lg;

        $typology = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        if(isset($options['checkin'])) {
            if(empty($typology)) {
                return null;
            } else {
                $typology=$typology[0];
            }
        }

        $equipment_array = $this->api("/typologies/" . $typology['id'] . "/equipment");
        $equipments = array();
        $not_equipments = array(
            'id',
            'typology_id',
            'creation_date',
            'creation_usr',
            'edition_date',
            'edition_usr'
        );
        foreach ($equipment_array as $equipment_name => $equipment_value) {
            if (strpos($equipment_name, '_model') == false && in_array($equipment_name, $not_equipments, true) == false) {
                $equipments[$equipment_name] = $equipment_value;
            }
        }

        $images_array = $this->api("/typologies/" . $typology['id'] . "/images");

        $images = array();
        foreach ($images_array as $image) {
            $images[$image['id']] = array(
                'id' => $image['id'],
                'name' => $image['name'],
                'description' => array(
                    'es' => strip_tags($image['description_es']) ,
                    'ca' => strip_tags($image['description_ca']) ,
                    'en' => strip_tags($image['description_en']) ,
                    'fr' => strip_tags($image['description_fr']) ,
                    'de' => strip_tags($image['description_de']) ,
                    'nl' => strip_tags($image['description_nl']) ,
                    'it' => strip_tags($image['description_it']) ,
                    'ru' => strip_tags($image['description_ru'])
                ) ,
                'image' => sprintf('%s/typologies/%s/images/%s/image.jpg?max_w=%s&max_h=%s&quality=%s',
                        $this->apiBaseUrl,
                        $image['typology_id'],
                        $image['id'],
                        $params['max_w'],
                        $params['max_h'],
                        $params['quality']
                )
            );
        }

        $property = array(
            'id' => $typology['id'],
            'name' => $typology['name'],
            'ref' => $typology['property_ref_property_string'],
            'generalitat_reference' => $typology['property_generalitat_reference'],
            'toilets' => $typology['toilets'],
            'capacity' => $typology['capacity'],
            'building_type' => $typology['building_type'],
            'commercialization_type' => $typology['commercialization_type'],
            'rent_type' => $typology['rent_type'],
            'description' => array(
                'es' => strip_tags($typology['description_es']) ,
                'ca' => strip_tags($typology['description_ca']) ,
                'en' => strip_tags($typology['description_en']) ,
                'fr' => strip_tags($typology['description_fr']) ,
                'de' => strip_tags($typology['description_de']) ,
                'nl' => strip_tags($typology['description_nl']) ,
                'it' => strip_tags($typology['description_it']) ,
                'ru' => strip_tags($typology['description_ru'])
            ) ,
            'address' => array(
                'address' => $typology['building_address'],
                'urbanization' => $typology['building_urbanization'],
                'zipcode' => $typology['building_cp'],
                'zone' => $typology['building_zone'],
                'country' => $typology['building_country_iso'],
                'province' => $typology['building_province'],
                'city' => $typology['building_city'],
                'latitude' => $typology['building_lat'],
                'longitude' => $typology['building_long']
            ) ,
            'equipment' => $equipments,
            'average_evaluation' => $typology['average_evaluation'],
            'room_number' => $typology['room_number'],
            'price' => $typology['rent_price'],
            'price_offer' => $typology['rent_price_offer'],
            'sell_price' => $typology['sell_price'],
            'sell_price_offer' => $typology['sell_price_offer'],
            'price_from' => isset($typology['price_from']) ? $typology['price_from'] : 0,
            'totalprice' => isset($typology['totalprice']) ? $typology['totalprice'] : 0,
            'finalprice' => isset($typology['finalprice']) ? $typology['finalprice'] : 0,

            'online_reservation' => $typology['default_online_reservation'],

            'bathroom_tub'=> $typology['property_bathroom_tub'],
            'bathroom_shower'=> $typology['property_bathroom_shower'],
            'total_toilets' => $typology['toilets']+$typology['property_bathroom_tub']+$typology['property_bathroom_shower'],
            'floor'=> $typology['property_floor'],
            'lift'=> $typology['property_lift'],
            'parking'=> $typology['parking'],
            'garage'=> $typology['garage'],
            'views'=> $typology['property_views'],
            'garden'=> $typology['garden'],
            'pool_type'=> $typology['pool_type'],
            'sea_distance'=> $typology['sea_distance'],

            'double_beds'=> $typology['property_double_beds'],
            'single_beds'=> $typology['property_single_beds'],
            'bunk_beds'=> $typology['property_bunk_beds'],
            'babycots'=> $typology['property_babycots'],
            'sofa_beds'=> $typology['property_sofa_beds'],
            'kitchen_type'=> $typology['property_kitchen_type'],
            'pets'=> $typology['property_pets'],

            'balcony'=> $typology['balcony'],
            'terrace'=> $typology['terrace'],
            'porsch'=> $typology['porsch'],
            'garden'=> $typology['garden'],
            'wheelchair_access'=> $typology['property_wheelchair_access'],

            'property_meters'=> $typology['property_property_meters'],
            'parcel_meters'=> $typology['property_parcel_meters'],
            'construction_year'=> $typology['building_construction_year'],

            'agency_id'=> $typology['prices_agency_id'],

            'images' => $images,

            'creation_date' => isset($typology['creation_date']) ? $typology['creation_date'] : null,
            'edition_date' => isset($typology['edition_date']) ? $typology['edition_date'] : null
        );

        return $property;
    }

    public function getAvailability($property_id, array $options = array())
    {
    }

    protected function checkDateFormat($date)
    {
        $matches=array();
        if (preg_match('#^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})#', $date, $matches)) {
            $date = sprintf('%d-%d-%d', $matches[3], $matches[2], $matches[1]);
        }
        return $date;
    }

    public function api($endPoint, $type = 'GET', $params = array() , $options = array())
    {
        if (!preg_match('#\:\/\/#', $endPoint)) {
            $endPoint = $this->apiBaseUrl . $endPoint;
        }
        return $this->makeRequest($endPoint, $type, $params, $options);
    }

    protected function makeRequest($url, $method = 'GET', $params = array() , $options = array() , $ch = null)
    {
        if (!$ch) {
            $ch = curl_init();
        }

        $opts = self::$CURL_OPTS;
        if ($this->apiUser && $this->apiPassword) {
            $opts[CURLOPT_USERPWD] = $this->apiUser . ":" . $this->apiPassword;
        }

        //        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
        $opts[CURLOPT_URL] = $url;

        // disable the 'Expect: 100-continue' behaviour. This causes CURL to wait
        // for 2 seconds if the server does not support this header.
        if (isset($opts[CURLOPT_HTTPHEADER])) {
            $existing_headers = $opts[CURLOPT_HTTPHEADER];

            // $existing_headers[] = 'Expect:';
            $existing_headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $existing_headers;
        } else {
            $opts[CURLOPT_HTTPHEADER] = array(

                // 'Expect: application/json',
                'Content-Type: application/json'
            );
        }

        if (isset($options['headers']) && count($options['headers']) > 0) {
            foreach ((array)$options['headers'] as $header) {
                $opts[CURLOPT_HTTPHEADER][] = $header;
            }
        }

        $rawBody = null;
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if (in_array($method, array(
            'POST',
            'PUT',
            'PATCH'
        ))) {

            //            $rawBody = http_build_query($params, null, '&');
            // $rawBody = json_encode($params);
            $opts[CURLOPT_POSTFIELDS] = (isset($options['no_json_encode'])) ? $params : json_encode($params);
            $opts[CURLOPT_POST] = 1;
        }

        curl_setopt_array($ch, $opts);
        $time = microtime(true);

        $result = curl_exec($ch);

        // With dual stacked DNS responses, it's possible for a server to
        // have IPv6 enabled but not have IPv6 connectivity.  If this is
        // the case, curl will try IPv4 first and if that fails, then it will
        // fall back to IPv6 and the error EHOSTUNREACH is returned by the
        // operating system.
        if ($result === false && empty($opts[CURLOPT_IPRESOLVE])) {
            $matches = array();
            $regex = '/Failed to connect to ([^:].*): Network is unreachable/';
            if (preg_match($regex, curl_error($ch) , $matches)) {
                if (strlen(@inet_pton($matches[1])) === 16) {
                    self::errorLog('Invalid IPv6 configuration on server, ' . 'Please disable or get native IPv6 on your server.');
                    self::$CURL_OPTS[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
                    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    $result = curl_exec($ch);
                }
            }
        }

        if ($result === false) {
            $e = new Exception(
                curl_error($ch),
                curl_errno($ch),
                null,
                $url,
                $params,
                null,
                null
            );
            curl_close($ch);
            throw $e;
        }

        $response = json_decode($result, true);
        if ($response === false) {
            $response = $result;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!($httpCode >= 200 && $httpCode < 300)) {
            $errorMsg = (isset($response['n2rmsg'])) ? $response['n2rmsg'] : curl_error($ch);
            $e = new Exception(
                ($errorMsg) ? $errorMsg : sprintf('HttpCode %d is not valid!', $httpCode) ,
                (curl_errno($ch)) ? curl_errno($ch) : $httpCode,
                (isset($response['n2rcode'])) ? $response['n2rcode'] : null,
                $url,
                $params,
                $response,
                $result
            );
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);

        return $response;
    }

    public function sendFile($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl . $url);

        if ($this->apiUser && $this->apiPassword) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->apiUser . ":" . $this->apiPassword);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
