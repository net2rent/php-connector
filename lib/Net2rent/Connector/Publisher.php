<?php
namespace Net2rent\Connector;

class Publisher extends AbstractConnector
{

    protected $_endPoints = array(
        'properties' => '/portals/{{company}}/properties',
        'property' => '/companies/{{company}}/properties/%s',
    );
}
