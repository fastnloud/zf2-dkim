<?php

namespace Dkim\Signer;

use Dkim\Header\Dkim;
use Zend\Mail\Header\GenericHeader;
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mail\Header;

/**
 * Signer.
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
        // optional params having a default value set
        'v'  => '1',
        'a'  => 'rsa-sha1',

        // required to set either in your config file or through the setParam method before signing (see
        // module.config.dist file)
        'd'  => '', // domain
        'h'  => '', // headers to sign
        's'  => '', // domain key selector
    );

    /**
     * Empty DKIM header.
     *
     * @var Dkim
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
     * @var bool|OpenSSL key
     */
    private $privateKey = false;

    /**
     * Set and validate DKIM options.
     *
     * @param array $options
     * @throws \Exception
     * @return \Dkim\Signer\Signer
     */
    public function __construct(array $options)
    {
        if (!isset($options['dkim'])) {
            throw new \Exception("No 'dkim' config option set.");
        } else {
            $config = $options['dkim'];

            if (isset($config['private_key']) && !empty($config['private_key'])) {
                $this->setPrivateKey($config['private_key']);
            }

            if(isset($config['params']) && is_array($config['params']) && !empty($config['params'])) {
                foreach ($config['params'] as $key => $value) {
                    $this->setParam($key, $value);
                }
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
     * Set Dkim param.
     *
     * @param string $key
     * @param string $value
     * @throws \Exception
     * @return void
     */
    public function setParam($key, $value)
    {
        if (!array_key_exists($key, $this->getParams())) {
            throw new \Exception("Invalid param '$key' given.");
        }

        $this->params[$key] = $value;
    }

    /**
     * Set multiple Dkim params.
     *
     * @param array $params
     * @return void
     */
    public function setParams(array $params)
    {
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $this->setParam($key, $value);
            }
        }
    }

    /**
     * Set (generate) OpenSSL key.
     *
     * @param string $privateKey
     * @throws \Exception
     * @return void
     */
    public function setPrivateKey($privateKey)
    {
        $privateKey = <<<PKEY
-----BEGIN RSA PRIVATE KEY-----
$privateKey
-----END RSA PRIVATE KEY-----
PKEY;

        if (!$privateKey = @openssl_pkey_get_private($privateKey)) {
            throw new \Exception("Invalid private key given.");
        }

        $this->privateKey = $privateKey;
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
        return trim(preg_replace('~\R~u', "\r\n", $string)) . "\r\n";
    }

    /**
     * Canonize headers for signing.
     *
     * @param Message $message
     * @return void
     */
    private function canonizeHeaders(Message $message)
    {
        $params  = $this->getParams();
        $headersToSign = explode(':', $params['h']);
        if (!in_array('dkim-signature', $headersToSign)) {
            $headersToSign[] = 'dkim-signature';
        }

        foreach($headersToSign as $fieldName) {
            $fieldName = strtolower($fieldName);
            $header = $message->getHeaders()->get($fieldName);
            if ($header instanceof Header\HeaderInterface) {
                $this->appendCanonizedHeader(
                    $fieldName . ':' . preg_replace('/\s+/', ' ', $header->getFieldValue(Header\HeaderInterface::FORMAT_ENCODED)) . "\r\n"
                );
            }
        }
    }

    /**
     * Generate empty DKIM header.
     *
     * @param Message $message
     * @throws \Exception
     * @return void
     */
    private function generateEmptyDkimHeader(Message $message)
    {
        // fetch configurable params
        $configurableParams = $this->getParams();

        // check if the required params are set for singing.
        if (empty($configurableParams['d']) || empty($configurableParams['h']) || empty($configurableParams['s'])) {
            throw new \Exception('Unable to sign message: missing params');
        }

        // final params
        $params = array(
            'v'    => $configurableParams['v'],
            'a'    => $configurableParams['a'],
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

        // set empty dkim header
        $this->setEmptyDkimHeader(new Dkim(substr(trim($string),0, -1)));
    }

    /**
     * Generate signature.
     *
     * @return string
     * @throws \Exception
     */
    private function generateSignature()
    {
        if (!$this->getPrivateKey()) {
            throw new \Exception('No private key given.');
        }

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

        // generate new header set starting with the dkim header
        $headerSet[] = new Dkim($this->getEmptyDkimHeader()->getFieldValue() . $signature);

        // then append existing headers
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
     * @param \Dkim\Header\Dkim $emptyDkimHeader
     * @return void
     */
    private function setEmptyDkimHeader(Dkim $emptyDkimHeader)
    {
        $this->emptyDkimHeader = $emptyDkimHeader;
    }

    /**
     * Get empty DKIM header.
     *
     * @return GenericHeader
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
     * Return OpenSSL key resource.
     *
     * @return OpenSSL key
     */
    private function getPrivateKey()
    {
        return $this->privateKey;
    }

}