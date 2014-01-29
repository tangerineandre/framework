<?php
namespace Phidias\Util\Form\Field\Element;

use Phidias\Util\Form\Field\Element;
use Phidias\HTTP\Request;

class Captcha extends Element
{
    public function toHTML()
    {
        require_once('recaptcha/recaptchalib.php');

        $publickey = $this->getOption('publicKey');
        return recaptcha_get_html($publickey);
    }

    public function filter($value)
    {
        require_once('recaptcha/recaptchalib.php');

        $resp = recaptcha_check_answer(
            $this->getOption('privateKey'),
            $_SERVER["REMOTE_ADDR"],
            Request::POST("recaptcha_challenge_field"),
            Request::POST("recaptcha_response_field")
        );

        return $resp->is_valid;
    }

    protected function validate($value)
    {
        if (!$value) {
            $this->addError('wrong captcha');
        }
    }
}