<?php
namespace Net2rent\Connector;

class Publisher extends AbstractConnector
{

    protected $_endPoints = array(
        'properties' => '/companies/{{company}}/properties',
        'property' => '/companies/{{company}}/properties/%s',
        'property_equipment' => '/properties/%s/equipment',
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
            $equipment_array = $this->api($this->getEndPoint('property_equipment', array($remoteProperty['id'])));
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

    public function insertProperty($properyOptions)
    {
        $endPoint = $this->getEndPoint('properties');
        $this->api($endPoint, 'POST', $properyOptions);
    }

    public function updateProperty($propertyId, $properyOptions)
    {
        $endPoint = $this->getEndPoint('property', array($propertyId));
        $this->api($endPoint, 'PUT', $properyOptions);
    }
}
