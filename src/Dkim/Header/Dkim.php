<?php

namespace Dkim\Header;

use Zend\Mail\Header\Exception\InvalidArgumentException;
use Zend\Mail\Header\HeaderInterface;
use Zend\Mail\Header\Exception;

class Dkim implements HeaderInterface
{

    /**
     * @var string
     */
    protected $value;

    /**
     * @param string $headerLine
     * @return HeaderInterface|static
     * @throws InvalidArgumentException
     */
    public static function fromString($headerLine)
    {
        list($name, $value) = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'dkimsignature') {
            throw new InvalidArgumentException('Invalid header line for DKIM-Signature string');
        }

        $header = new static($value);

        return $header;
    }

    /**
     * @param $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return 'DKIM-Signature';
    }

    /**
     * @param bool $format
     * @return string
     */
    public function getFieldValue($format = HeaderInterface::FORMAT_RAW)
    {
        return $this->value;
    }

    /**
     * @param string $encoding
     * @return $this|HeaderInterface
     */
    public function setEncoding($encoding)
    {
        // This header must be always in US-ASCII
        return $this;
    }

    /**
     * @return string
     */
    public function getEncoding()
    {
        return 'ASCII';
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'DKIM-Signature: ' . $this->getFieldValue();
    }

}