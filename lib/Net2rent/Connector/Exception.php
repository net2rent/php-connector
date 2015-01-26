<?php
namespace Net2rent\Connector;

class Exception extends \Exception
{
    protected $n2rCode;
    protected $url;
    protected $params = array();
    protected $result = array();
    protected $resultRaw;

    public function __construct($message = null, $code = 0, $n2rCode = null, $url = null, $params = array() , $result = array() , $resultRaw = null, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->setN2rCode($n2rCode)->setParams($params)->setUrl($url)->setResult($result)->setResultRaw($resultRaw);
    }

    /**
     * Gets the value of params.
     *
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Sets the value of params.
     *
     * @param mixed $params the params
     * @return self
     */
    public function setParams($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * Gets the value of result.
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Sets the value of result.
     *
     * @param mixed $result the result
     * @return self
     */
    public function setResult($result)
    {
        $this->result = $result;
        return $this;
    }

    /**
     * Gets the value of resultRaw.
     *
     * @return string
     */
    public function getResultRaw()
    {
        return $this->resultRaw;
    }

    /**
     * Sets the value of resultRaw.
     *
     * @param string $resultRaw the result raw
     * @return self
     */
    public function setResultRaw($resultRaw)
    {
        $this->resultRaw = (string) $resultRaw;
        return $this;
    }

    /**
     * Gets the value of n2rCode.
     *
     * @return string
     */
    public function getN2rCode()
    {
        return $this->n2rCode;
    }

    /**
     * Sets the value of n2rCode.
     *
     * @param string $n2rCode the n2r code
     * @return self
     */
    public function setN2rCode($n2rCode)
    {
        $this->n2rCode = (string) $n2rCode;
        return $this;
    }

    /**
     * Gets the value of url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Sets the value of url.
     *
     * @param string $url the url
     * @return self
     */
    public function setUrl($url)
    {
        $this->url = (string) $url;
        return $this;
    }
}
