<?php

namespace app\controllers;

use app\models\Transaction;

use sizeg\jwt\JwtHttpBearerAuth;

use Yii;

use yii\filters\ContentNegotiator;
use yii\filters\Cors;

use yii\rest\ActiveController;
use yii\rest\Serializer;

use yii\web\Response;

class TransactionController extends ActiveController
{
    /**
     * @inheritdoc
     */
    public $modelClass = Transaction::class;

    /**
     * @inheritdoc
     */
    public $serializer = [
        'class' => Serializer::class,
        'collectionEnvelope' => 'items',
    ];

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        unset($behaviors['authenticator']);

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];
        $behaviors['authenticator'] = [
			'class' => JwtHttpBearerAuth::class,
			'except' => [
				'options',
			],
		];
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        
        return $behaviors;
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        $actions = parent::actions();

        unset($actions['view'], $actions['update'], $actions['delete']);

        $actions['index']['prepareSearchQuery'] = [$this, 'prepareSearchQuery'];

        return $actions;
    }

    public function prepareSearchQuery($query, $requestParams)
    {
        $query->andFilterWhere(['user_id' => Yii::$app->user->id]);

        return $query;
    }
}
