<?php

class opPopMailPluginMailcheckTask extends sfBaseTask
{
  protected function configure()
  {
    $this->namespace        = 'opPopMailPlugin';
    $this->name             = 'mail-check';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [opPopMailPlugin:mailcheck|INFO] task does things.
Call it with:

  [./symfony opPopMailPlugin:mail-check|INFO]
EOF;
  }
  
  protected function execute($arguments = array(), $options = array())
  {
    require(dirname(dirname(__FILE__)).'/vendor/PEAR/Net/POP3.php');
    
    sfConfig::set('sf_test', true);
    
    
    $configuration = ProjectConfiguration::getApplicationConfiguration('mobile_mail_frontend', 'prod', false);

    $server = sfConfig::get('app_pop_mail_host');
    $user = sfConfig::get('app_pop_mail_user');
    $password = sfConfig::get('app_pop_mail_password');
    $port = sfConfig::get('app_pop_mail_port', 110);
    if($server=='' || $user=='' || $password=='')
    {
      throw new Exception('No POP3 configuration');
    }
    
    
    $pop3 = & new Net_POP3();
    $pop3->connect($server, $port);
    $pop3->login($user, $password);
    
    $messageCount = $pop3->numMsg();
    if(!$messageCount)
    {
      throw new Exception('No Message');
    }
    $maxMessageCount = sfConfig::get('app_pop_mail_max_mail_per_cron', 0);
    if($maxMessageCount > 0 && $messageCount > $maxMessageCount)
    {
      $messageCount = $maxMessageCount;
    }
    
    $mails = array();
    for($i=1;$i<=$messageCount;$i++)
    {
      $mails[] = $pop3->getMsg($i);
      $pop3->deleteMsg($i);
    }
    
    $pop3->disconnect();

    foreach($mails as $mail)
    {
      sfOpenPNEApplicationConfiguration::registerZend();
      
      $message = new opMailMessage(array('raw' => $mail));
      opMailRequest::setMailMessage($message);
      
      sfOpenPNEApplicationConfiguration::unregisterZend();
      
      $context = sfContext::createInstance($configuration);
      $request = $context->getRequest();
      
      ob_start();
      $context->getController()->dispatch();
      $retval = ob_get_clean();
      
      if ($retval)
      {
        $subject = $context->getResponse()->getTitle();
        $to = $message->from;
        $from = $message->to;
        sfOpenPNEMailSend::execute($subject, $to, $from, $retval);
      }
    }
  }
}