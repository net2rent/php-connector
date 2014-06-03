<?php
namespace Net2rent\Connector;

class Publisher extends AbstractConnector
{

    protected $_endPoints = array(
        'properties' => '/companies/{{company}}/properties',
        'property' => '/companies/{{company}}/properties/%s',
        'property_equipment' => '/properties/%s/equipment',
        'typology_images' => '/typologies/%s/images',
        'property_propertystatus' => '/properties/%s/propertystatus',
        'typology_prices' => '/typologies/%s/pricecalendar',
        'typology_property' => '/properties',
    );

    public function getProperties(array $options = array())
    {
        $endPoint = $this->getEndPoint('properties');

        $params = array();
        if (isset($options['external_ref'])) {
            $params['external_ref'] = $options['external_ref'];
        }
        $params['lg'] = $this->lg;
        $remoteProperties = $this->api(sprintf($endPoint . '?%s', http_build_query($params)));
        $remotePropertiesTotal = $this->api(sprintf($endPoint . '/size?%s', http_build_query($params)));

        $properties = array();
        foreach ($remoteProperties as $remoteProperty) {
            $equipment_array = $this->api($this->getEndPoint('property_equipment', array(
                $remoteProperty['id']
            )));
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
            $remoteProperty['equipment'] = $equipments;

            $properties[] = $remoteProperty;
        }

        return array(
            'total' => $remotePropertiesTotal['size'],
            'items' => $properties
        );
    }

    public function insertProperty(array $properyOptions)
    {
        $endPoint = $this->getEndPoint('properties');
        return $this->api($endPoint, 'POST', $properyOptions);
    }

    public function insertPropertyIntoTypology(array $properyOptions)
    {
        $endPoint = $this->getEndPoint('typology_property');
        return $this->api($endPoint, 'POST', $properyOptions);
    }

    public function updateProperty($propertyId, array $properyOptions)
    {
        $endPoint = $this->getEndPoint('property', array(
            $propertyId
        ));
        return $this->api($endPoint, 'PUT', $properyOptions);
    }

    public function updateImages($typologyId, array $images)
    {
        $endPoint = $this->getEndPoint('typology_images', array(
            $typologyId
        ));
        $currentImages = $this->api($endPoint);

        $imagesToInsert = array();
        foreach ($images as $image) {
            $exists = false;
            foreach ($currentImages as $currentImage) {
                if ($currentImage['name'] == $image['image']) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $imagesToInsert[] = $image;
            }
        }

        $tmpfile = tempnam(sys_get_temp_dir() , 'n2rimage');
        foreach ($imagesToInsert as $image) {
            $response = $this->api($endPoint, 'POST', array(
                'name' => $image['image'],
                'active' => true
            ));
            $imageId = $response['id'];

            $endPointImage = sprintf($endPoint . '/%s/image', $imageId);

            $imageBinary = $this->getBinaryFromFile($image['image']);
            if ($imageBinary) {
                file_put_contents($tmpfile, $imageBinary);
                $cfile = new \CURLFile($tmpfile);
                $this->sendFile($endPointImage, array(
                    'file' => $cfile
                ));
                unlink($tmpfile);
            }
        }
    }

    public function updateEquipment($propertyId, array $equipment)
    {
        $endPoint = $this->getEndPoint('property_equipment', array(
            $propertyId
        ));
        $existEquipment = $this->api($endPoint);
        $requestType = (isset($existEquipment['id']) && $existEquipment['id']) ? 'PUT' : 'POST';

        return $this->api($endPoint, $requestType, $equipment);
    }

    public function updatePropertyStatus($propertyId, array $availability)
    {
        $endPoint = $this->getEndPoint('property_propertystatus', array(
            $propertyId
        ));
        return $this->api($endPoint, 'PUT', $availability);
    }

    public function updateTypologyPrices($typologyId, array $prices)
    {
        $endPoint = $this->getEndPoint('typology_prices', array(
            $typologyId
        ));
        return $this->api($endPoint, 'PUT', $prices);
    }

    protected function getBinaryFromFile($filepath)
    {
        return @file_get_contents($filepath);
    }
}
