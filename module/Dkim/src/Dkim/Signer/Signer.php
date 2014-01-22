<?php

namespace Dkim\Signer;

use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mail\Header;

/**
 * Class Signer;
 * @package Dkim\Signer
 */
class Signer
{

    /**
     * All configurable params.
     *
     * @var array
     */
    private $params = array(
        'd'  => '',
        'h'  => '',
        's'  => ''
    );

    /**
     * Empty DKIM header.
     *
     * @var Header\GenericHeader
     */
    private $emptyDkimHeader;

    /**
     * Canonized headers.
     *
     * @var string
     */
    private $canonizedHeaders;

    /**
     * The private key being used.
     *
     * @var OpenSSL key
     */
    private $privateKey;

    /**
     * Set and validate DKIM options.
     *
     * @param $options
     * @return void
     */
    public function __construct(array $options)
    {
        if (!isset($options['dkim'])) {
            throw new \Exception("No 'dkim' config option set.");
        } else {
            $config = $options['dkim'];

            if (isset($config['private_key'])) {
                $this->setPrivateKey($config['private_key']);
            } else {
                throw new \Exception("No 'private_key' given.");
            }

            if(isset($config['params']) && is_array($config['params'])) {
                foreach ($this->getParams() as $key => $value) {
                    if (!isset($config['params'][$key])) {
                        throw new \Exception("No DKIM param '$key' given.");
                    }
                }

                $this->setParams($config['params']);
            } else {
                throw new \Exception("No 'params' given.");
            }
        }
    }

    /**
     * Sign message with a DKIM signature.
     *
     * @param Message $message
     * @return void
     */
    public function signMessage(Message &$message)
    {
        // format message
        $this->formatMessage($message);

        // generate empty dkim header including the body hash
        $this->generateEmptyDkimHeader($message);

        // add empty (unsigned) dkim header
        $message->getHeaders()->addHeader($this->getEmptyDkimHeader());

        // canonize headers for signing
        $this->canonizeHeaders($message);

        // sign message
        $this->sign($message);
    }

    /**
     * Format message for singing.
     *
     * @param Message $message
     * @return void
     */
    private function formatMessage(Message &$message)
    {
        $body = $message->getBody();

        if ($body instanceof MimeMessage) {
            $body = $body->generateMessage();
        }

        $body = $this->normalizeNewlines($body);
        if (!preg_match('/\r\n$/', $body)) {
            $body = $body . "\r\n";
        }

        $message->setBody($body);
    }

    /**
     * Normalize new lines to CRLF sequences.
     *
     * @param string $string
     * @return string
     */
    private function normalizeNewlines($string)
    {
        return preg_replace('~\R~u', "\r\n", $string);
    }

    /**
     * Canonize headers for signing.
     *
     * @param Message $message
     * @return void
     */
    private function canonizeHeaders(Message &$message)
    {
        $params  = $this->getParams();
        $headersToSign = explode(':', $params['h']);

        $headers = $message->getHeaders();
        foreach($headers as $header) {
            $fieldName = strtolower($header->getFieldName());

            if (in_array($fieldName, $headersToSign) || 'dkim-signature' == $fieldName) {
                $this->appendCanonizedHeader(
                    $fieldName . ':' . preg_replace('/\s+/', ' ', $header->getFieldValue()) . "\r\n"
                );
            }
        }
    }

    /**
     * Generate empty DKIM header.
     *
     * @param Message $message
     * @return void
     */
    private function generateEmptyDkimHeader(Message $message)
    {
        // fetch configurable params
        $configurableParams = $this->getParams();

        // final params
        $params = array(
            'v'    => '1',
            'a'    => 'rsa-sha1',
            'bh'   => $this->getBodyHash($message),
            'c'    => 'relaxed',
            'd'    => $configurableParams['d'],
            'h'    => $configurableParams['h'],
            's'    => $configurableParams['s'],
            'b'    => ''
        );

        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '; ';
        }

        $this->setEmptyDkimHeader(
            new Header\GenericHeader(
                'DKIM-Signature',
                substr(trim($string),0, -1)
            )
        );
    }

    /**
     * Generate signature.
     *
     * @return void
     */
    private function generateSignature()
    {
        $signature = '';
        openssl_sign($this->getCanonizedHeaders(), $signature, $this->getPrivateKey());

        return trim(chunk_split(base64_encode($signature), 73, ' '));
    }

    /**
     * Sign message.
     *
     * @param Message $message
     * @return void
     */
    private function sign(Message &$message)
    {
        // generate signature
        $signature = $this->generateSignature();

        // first remove the empty dkim header
        $message->getHeaders()->removeHeader('DKIM-Signature');

        // generate new header set
        $headerSet[] = new Header\GenericHeader(
            'DKIM-Signature',
            $this->getEmptyDkimHeader()->getFieldValue() . $signature
        );

        // append existing headers
        $headers = $message->getHeaders();
        foreach($headers as $header) {
            $headerSet[] = $header;
        }

        // clear headers
        $message->getHeaders()->clearHeaders();

        // add the newly created header set with the dkim signature
        $message->getHeaders()->addHeaders($headerSet);
    }

    /**
     * Set configurable params.
     *
     * @param array $params
     * @return void
     */
    private function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Get configurable params.
     *
     * @return array
     */
    private function getParams()
    {
        return $this->params;
    }

    /**
     * Set empty DKIM header.
     *
     * @param Header\GenericHeader $emptyDkimHeader
     * @return void
     */
    private function setEmptyDkimHeader(Header\GenericHeader $emptyDkimHeader)
    {
        $this->emptyDkimHeader = $emptyDkimHeader;
    }

    /**
     * Get emtpy DKIM header.
     *
     * @return Header\GenericHeader
     */
    private function getEmptyDkimHeader()
    {
        return $this->emptyDkimHeader;
    }

    /**
     * Append canonized header to raw canonized header
     * set.
     *
     * @param $canonizedHeader
     * @return void
     */
    private function appendCanonizedHeader($canonizedHeader)
    {
        $this->setCanonizedHeaders($this->canonizedHeaders . $canonizedHeader);
    }

    /**
     * Set canonized headers.
     *
     * @param $canonizedHeaders
     * @return void
     */
    private function setCanonizedHeaders($canonizedHeaders)
    {
        $this->canonizedHeaders = $canonizedHeaders;
    }

    /**
     * Get canonized headers.
     *
     * @return string
     */
    private function getCanonizedHeaders()
    {
        return trim($this->canonizedHeaders);
    }

    /**
     * Get Message body (sha1) hash.
     *
     * @param Message $message
     * @return string
     */
    private function getBodyHash(Message $message)
    {
        return base64_encode(pack("H*", sha1($message->getBody())));
    }

    /**
     * Set (generate) OpenSSL key.
     *
     * @param string $privateKey
     * @return void
     */
    private function setPrivateKey($privateKey)
    {
        $privateKey = <<<KEY
-----BEGIN RSA PRIVATE KEY-----
$privateKey
-----END RSA PRIVATE KEY-----
KEY;

        $this->privateKey = openssl_get_privatekey($privateKey);
    }

    /**
     * Return OpenSSL key resource.
     *
     * @return OpenSSL key
     */
    private function getPrivateKey()
    {
        return $this->privateKey;
    }

}