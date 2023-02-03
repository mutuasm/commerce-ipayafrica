<?php
/**
 * @link https://dimetech.com/
 * @copyright Copyright (c) Dimetech Group.
 */

namespace craft\commerce\ipayafrica\models;

use craft\commerce\models\payments\BasePaymentForm;

class iPayAfricaOffsitePaymentForm extends BasePaymentForm
{
	public $tel;
	public $eml;
	public $ttl;
	public $oid;
	public $curr;
	public $cbk;
	public $creditcard = 1;
}