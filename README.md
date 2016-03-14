README
========

Zend Framework 2 DKIM Signer.

**Installation**

Installation of this module uses composer. For composer documentation, please refer to [https://getcomposer.org](https://getcomposer.org/ "getcomposer.org").

<code>php composer.phar require fastnloud/zf2-dkim</code>

Create a *./config/autoload/dkim.global.php* and *./config/autoload/dkim.local.php* file with the configuration variable values as described in the project *.dist* files.

**Usage**

1. Create a DKIM domain key 
   - See: [http://dkimcore.org/specification.html](http://dkimcore.org/specification.html "dkimcore.org")

2. Configure the DkimSigner using the config.dist file

3. Sign & send

<code>
    **I.e. in your Controller:**
    
    $mail = new \Zend\Mail\Message();
    $mail->setBody("Hello world!");
    $mail->setFrom('from@example.com');
    $mail->addTo('to@example.com');
    $mail->setSubject('le subject');
    
    // sign message with dkim
    $signer = $this->getServiceLocator()->get('DkimSigner');
    $signer->signMessage($mail);
    
    // send message
    $transport = new \Zend\Mail\Transport\Sendmail();
    $transport->send($mail);
</code>

That's it.
