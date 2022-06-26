<?php

namespace app\controllers;

use app\models\User;

use Yii;

use yii\filters\ContentNegotiator;
use yii\filters\Cors;

use yii\rest\Controller;

use yii\web\BadRequestHttpException;
use yii\web\Response;
use yii\web\UnauthorizedHttpException;
use yii\web\UnprocessableEntityHttpException;

class UserController extends Controller
{
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
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        
        return $behaviors;
    }

    /**
     * {@inheritdoc}
     */
    protected function verbs()
    {
        return [
            'sms' => ['POST'],
            'login' => ['POST'],
        ];
    }

    public function actionSms()
    {
        $phone_number = Yii::$app->request->post('phone_number');

        if (!preg_match('/^[0-9]{10}+$/', $phone_number)) {
            throw new BadRequestHttpException('Неправильный формат номера телефона');
        }

        $confirmation_code = random_int(0, 9) . random_int(0, 9) . random_int(0, 9) . random_int(0, 9);

        if (!$this->sendSms($phone_number, $confirmation_code)) {
        // if (!true) {
            throw new UnprocessableEntityHttpException('Неправильный номер телефона');
        }

        if (!$user = User::findIdentityByPhoneNumber($phone_number)) {
            $user = new User;
            $user->phone_number = $phone_number;
        }

        $user->setConfirmationCode($confirmation_code);
        $user->save();

        return [
            'message' => "Код подтверждения на номер $phone_number успешно отправлен.",
            'status' => 200,
        ];
    }

    public function actionLogin()
    {
        $phone_number = Yii::$app->request->post('phone_number');
        $confirmation_code = Yii::$app->request->post('confirmation_code');

        if (!preg_match('/^[0-9]{10}+$/', $phone_number)) {
            throw new BadRequestHttpException('Неправильный формат номера телефона');
        }

        if (!preg_match('/^[0-9]{4}+$/', $confirmation_code)) {
            throw new BadRequestHttpException('Неправильный формат проверочного кода');
        }

        if (!$user = User::findIdentityByPhoneNumber($phone_number)){
            throw new UnauthorizedHttpException('Неправильный номер телефона.');
        }

        if (!$user->validateConfirmationCode($confirmation_code)) {
            throw new UnauthorizedHttpException('Неправильный проверочный код.');
        }

        $token = $this->generateJwt($user);

        return [
            'message' => 'Авторизация прошла успешно.',
            'status' => 200,
            'token' => (string) $token,
        ];
    }

    private function generateJwt(User $user): \Lcobucci\JWT\Token
    {
        /**
         * @var \sizeg\jwt\Jwt
         */
		$jwt = Yii::$app->jwt;
		$signer = $jwt->getSigner('HS256');
		$key = $jwt->getKey();
		$time = time();

		$jwtParams = Yii::$app->params['jwt'];

		return $jwt->getBuilder()
			->issuedBy($jwtParams['issuer'])
			->identifiedBy($jwtParams['id'], true)
			->issuedAt($time)
            ->expiresAt($time + $jwtParams['expire'])
			->withClaim('uid', $user->getId())
			->getToken($signer, $key);
	}

    private function sendSms(string $phone_number, string $confirmation_code): bool
    {
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://smsc.ru/sys/']);

        $smscParams = Yii::$app->params['smsc'];
        
        $response = $client->get('send.php', [
            'query' => [
                'login' => $smscParams['login'],
                'psw' => $smscParams['password'],
                'phones' => $smscParams['calling_code'] . $phone_number,
                'mes' => 'Ваш код подтверждения: ' . $confirmation_code,
                'fmt' => 3,
            ],
        ]);

        $body = json_decode($response->getBody(), true);

        if (!isset($body['error'])) {
            return true;
        }

        return false;
    }
}
