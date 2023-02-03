<?php
/**
 * @link      https://dimetechgroup.com/
 * @copyright Copyright (c) Dimetech Group.
 * @license   
 */

namespace craft\commerce\ipayafrica\migrations;

use Craft;
use craft\commerce\ipay\gateways\Gateway;
use craft\db\Migration;
use craft\db\Query;
use yii\db\Exception;

/**
 * Installation Migration
 *
 * @author Mwanzia Mutua. <support@dimetechgroup.com>
 * @since  1.0
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Convert any built-in iPayAfrica gateways to ours
        $this->_convertGateways();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return true;
    }

    /**
     * Converts any old school iPayAfrica gateways to this one
     *
     * @return void
     * @throws Exception
     */
    private function _convertGateways(): void
    {
        $gateways = (new Query())
            ->select(['id'])
            ->where(['type' => 'craft\\commerce\\gateways\\ipayafrica'])
            ->from(['{{%commerce_gateways}}'])
            ->all();

        $dbConnection = Craft::$app->getDb();

        foreach ($gateways as $gateway) {
            $values = [
                'type' => Gateway::class,
            ];

            $dbConnection->createCommand()
                ->update('{{%commerce_gateways}}', $values, ['id' => $gateway['id']])
                ->execute();
        }
    }
}
