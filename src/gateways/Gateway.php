<?php
/**
 * @link https://dimetechgroup.com/
 * @copyright Copyright (c) Dimetech Group.
 * @license 
 */

namespace craft\commerce\ipayafrica\gateways;

use Craft;
use craft\commerce\base\RequestResponseInterface;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\OrderStatusException;
use craft\commerce\errors\TransactionException;
use craft\commerce\ipayafrica\models\iPayAfricaOffsitePaymentForm;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\commerce\ipayafrica\models\forms\IpayOffsitePaymentForm;
use craft\commerce\ipayafrica\models\RequestResponse;
use craft\commerce\omnipay\base\OffsiteGateway;
use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\errors\ElementNotFoundException;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Response;
use craft\web\View;
use Omnipay\Common\AbstractGateway;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Issuer;
use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\ResponseInterface;
use Omnipay\Common\PaymentMethod;
use Omnipay\iPayAfrica\Gateway as OmnipayGateway;
use Omnipay\iPayAfrica\Message\PurchaseRequestKenya;
use Omnipay\iPayAfrica\Message\CompletePurchaseRequestKenya;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;

/**
 * Gateway represents iPayAfrica gateway
 *
 * @author    Dimetech Group. <support@dimetechgroup.com>
 * @since     1.0
 *
 * @property string $password
 * @property-read null|string $settingsHtml
 */
class Gateway extends OffsiteGateway
{
    /**
     * @var string|null
     */
    private ?string $_password = null;
    private ?string $_username = null;

    /**
     * @var array|null
     */
    private ?array $_paymentMethods = null;

    /**
     * @inheritdoc
     */
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $settings['password'] = $this->getPassword(false);
        $settings['username'] = $this->getUsername(false);

        return $settings;
    }


    public function getPassword(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_password) : $this->_password;
    }

    /**
     * @param string|null $password
     * @return void
     * @since 4.0.0
     */
    public function setPassword(?string $password): void
    {
        $this->_password = $password;
    }

        /**
     * @param bool $parse
     * @return string|null
     * @since 4.0.0
     */
    public function getUsername(bool $parse = true): ?string
    {
        return $parse ? App::parseEnv($this->_username) : $this->_username;
    }

    /**
     * @param string|null $username
     * @return void
     * @since 4.0.0
     */
    public function setUsername(?string $username): void
    {
        $this->_username = $username;
    }

        /**
     * @inheritdoc
     */
    public function supportsPaymentSources(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsRefund(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function supportsCompletePurchase(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function supportsWebhooks(): bool
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    public function populateRequest(array &$request, BasePaymentForm $paymentForm = null): void
    {
        if ($paymentForm) {
            /** @var iPayAfricaOffsitePaymentForm $paymentForm */
            if ($paymentForm->tel) {
                $request['tel'] = $paymentForm->tel;
            }
        }
		
		$request['eml'] = $request['order']['email'];
		$request['ttl'] = $request['amount'];
		$request['oid'] = $request['order']['id'];
		$request['curr'] = $request['currency'];
		$request['cbk'] = $request['returnUrl'];
		$request['debitcard'] = 1;
		$request['mpesa'] = 1;
		$request['airtel'] = 1;
		$request['equity'] = 1;
		$request['bonga'] = 1;
	
    }

    /**
     * @inheritDoc
     */
    public function purchase(Transaction $transaction, BasePaymentForm $form): RequestResponseInterface
    {
        return parent::purchase($transaction, $form);
    }


    /**
     * @inheritdoc
     */
    public function processWebHook(): Response
    {
        $rawData = Craft::$app->getRequest()->getRawBody();
        $response = Craft::$app->getResponse();
        $response->format = Response::FORMAT_RAW;

        $data = Json::decodeIfJson($rawData);

        if ($data) {
            try {

            } catch (\Throwable $exception) {
                Craft::$app->getErrorHandler()->logException($exception);
            }
        } else {
            Craft::warning('Could not decode JSON payload.', 'stripe');
        }

        $response->data = 'ok';

        return $response;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce', 'iPayAfrica');
    }


    /**
     * @inheritdoc
     */
    public function getPaymentTypeOptions(): array
    {
        return [
            'purchase' => Craft::t('commerce', 'Purchase (Authorize and Capture Immediately)'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('commerce-ipayafrica/gatewaySettings', ['gateway' => $this]);
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormModel(): BasePaymentForm
    {
        return new iPayAfricaOffsitePaymentForm();
    }

    /**
     * @inheritdoc
     */
    public function getPaymentFormHtml(array $params): ?string
    {
        try {
            $defaults = [
                'gateway' => $this,
                'paymentForm' => $this->getPaymentFormModel()                         
            ];
        } catch (\Throwable $exception) {
            // In case this is not allowed for the account
            return parent::getPaymentFormHtml($params);
        }        

        $params = array_merge($defaults, $params);

        $view = Craft::$app->getView();

        $previousMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_CP);

        $html = $view->renderTemplate('commerce-ipayafrica/paymentForm', $params);

        $view->setTemplateMode($previousMode);

        return $html;
    }


    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = ['paymentType', 'compare', 'compareValue' => 'purchase'];

        return $rules;
    }


    /**
     * @inheritdoc
     */
    protected function createGateway(): AbstractGateway
    {
        /** @var OmnipayGateway $gateway */
        $gateway = static::createOmnipayGateway($this->getGatewayClassName());

        $gateway->setPassword($this->getPassword());
        $gateway->setUsername($this->getUsername());

        return $gateway;
    }

    /**
     * @inheritdoc
     */
    protected function getGatewayClassName(): ?string
    {
        return '\\' . OmnipayGateway::class;
    }

    /**
     * @inheritdoc
     */
    protected function prepareResponse(ResponseInterface $response, Transaction $transaction): RequestResponseInterface
    {
        /** @var AbstractResponse $response */
        return new RequestResponse($response, $transaction);
    }
}
