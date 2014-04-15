<?php
namespace Net2rent\Connector;

class Publisher extends AbstractConnector
{

    protected $_endPoints = array(
        'properties' => '/companies/{{company}}/properties',
        'property' => '/companies/{{company}}/properties/%s',
    );
}
