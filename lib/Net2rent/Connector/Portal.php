<?php
namespace Net2rent\Connector;

class Portal extends AbstractConnector
{

    protected $_endPoints = array(
        'properties_availables' => '/portals/{{portal}}/typologies_availability',
        'properties' => '/portals/{{portal}}/typologies',
        'property_available' => '/portals/{{portal}}/typologies_availability/%s',
        'property' => '/portals/{{portal}}/typologies/%s',
    );
}
