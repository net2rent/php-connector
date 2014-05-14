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
    const VERSION = '0.2.0';

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
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'net2rent-connector-php-0.1.0',
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

    public function getCities()
    {
        return $this->api('/portals/' . $this->apiUser . '/properties-cities');
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
        if (isset($options['city'])) {
            $params['city'] = $options['city'];
        }
        if (isset($options['external_ref'])) {
            $params['external_ref'] = $options['external_ref'];
        }
        $params['lg'] = $this->lg;
        $typologies = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $typologiesTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

        $properties = array();
        foreach ($typologies as $typology) {
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
                'capacity' => $typology['capacity'],
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
                'online_reservation' => $typology['property_online_reservation'],
                'image' => (isset($typology['image_id'])) ? sprintf('%s/typologies/%s/images/%s/image', $this->apiBaseUrl, $typology['id'], $typology['image_id']) : null
            );
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
        $params['lg'] = $this->lg;

        $typology0 = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $typology = $typology0[0];

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
                'image' => sprintf('%s/typologies/%s/images/%s/image', $this->apiBaseUrl, $image['typology_id'], $image['id'])
            );
        }

        $property = array(
            'id' => $typology['id'],
            'name' => $typology['name'],
            'room_number' => $typology['room_number'],
            'toilets' => $typology['toilets'],
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
            'images' => $images
        );

        return $property;
    }

    public function getAvailability(int $property_id, array $options = array())
    {
    }

    protected function checkDateFormat($date)
    {
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
            $e = new \Exception(curl_error($ch) , curl_errno($ch));
            curl_close($ch);
            throw $e;
        }

        $response = json_decode($result, true);
        if ($response === false) {
            $response = $result;
        }

        // var_dump($result);exit;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!($httpCode >= 200 && $httpCode < 300)) {
            $errorMsg = (isset($response['n2rmsg'])) ? $response['n2rmsg'] : curl_error($ch);
            $e = new \Exception(($errorMsg) ? $errorMsg : sprintf('HttpCode %d is not valid!', $httpCode) , (curl_errno($ch)) ? curl_errno($ch) : $httpCode);
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
