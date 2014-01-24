README
========

Zend Framework 2 DKIM Signer.

**Usage**

1. Create a DKIM domain key 
   - See: [http://dkimcore.org/specification.html](http://dkimcore.org/specification.html "dkimcore.org")

2. Configure the DkimSigner using the config.dist file

3. Sign & send

<code>
    **I.e. in your Controller:**
    
    $mail = new Zend\Mail\Message();
    $mail->setBody("Hello world!");
    $mail->setFrom('from@example.com');
    $mail->addTo('to@example.com');
    $mail->setSubject('le subject');
    
    // sign message with dkim
    $signer = $this->getServiceLocator()->get('DkimSigner');
    $signer->signMessage($mail);
    
    // send message
    $transport = new Zend\Mail\Transport\Sendmail();
    $transport->send($mail);
</code>

That's it.