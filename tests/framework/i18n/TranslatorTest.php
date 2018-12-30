<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\tests\framework\i18n;

use yii\helpers\Yii;
use yii\base\Event;
use yii\i18n\Translator;
use yii\i18n\PhpMessageSource;
use yii\i18n\TranslationEvent;
use yii\tests\TestCase;

/**
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 * @group i18n
 */
class TranslatorTest extends TestCase
{
    /**
     * @var Translator
     */
    public $translator;

    protected function setUp()
    {
        parent::setUp();
        $this->mockApplication();
        $this->setTranslator();
        $this->i18n = $this->container->get('i18n');
    }

    protected function setTranslator()
    {
        $this->translator = $this->factory->create([
            '__class' => Translator::class,
            'translations' => [
                'test' => [
                    '__class' => $this->getMessageSourceClass(),
                    'basePath' => '@yii/tests/data/i18n/messages',
                ],
            ],
        ]);
    }

    public function testDI()
    {
        $translator = $this->container->get('translator');
        $this->assertInstanceOf(Translator::class, $translator);
    }

    private function getMessageSourceClass()
    {
        return PhpMessageSource::class;
    }

    public function testTranslate()
    {
        $msg = 'The dog runs fast.';

        // source = target. Should be returned as is.
        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', $msg, [], 'en-US'));

        // exact match
        $this->assertEquals('Der Hund rennt schnell.', $this->translator->translate('test', $msg, [], 'de-DE'));

        // fallback to just language code with absent exact match
        $this->assertEquals('Собака бегает быстро.', $this->translator->translate('test', $msg, [], 'ru-RU'));

        // fallback to just langauge code with present exact match
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
    }

    public function testDefaultSource()
    {
        $translator = $this->factory->create([
            '__class' => Translator::class,
            'translations' => [
                '*' => [
                    '__class' => $this->getMessageSourceClass(),
                    'basePath' => '@yii/tests/data/i18n/messages',
                    'fileMap' => [
                        'test' => 'test.php',
                        'foo' => 'test.php',
                    ],
                ],
            ],
        ]);

        $msg = 'The dog runs fast.';

        // source = target. Should be returned as is.
        $this->assertEquals($msg, $translator->translate('test', $msg, [], 'en-US'));

        // exact match
        $this->assertEquals('Der Hund rennt schnell.', $translator->translate('test', $msg, [], 'de-DE'));
        $this->assertEquals('Der Hund rennt schnell.', $translator->translate('foo', $msg, [], 'de-DE'));
        $this->assertEquals($msg, $translator->translate('bar', $msg, [], 'de-DE'));

        // fallback to just language code with absent exact match
        $this->assertEquals('Собака бегает быстро.', $translator->translate('test', $msg, [], 'ru-RU'));

        // fallback to just langauge code with present exact match
        $this->assertEquals('Hallo Welt!', $translator->translate('test', 'Hello world!', [], 'de-DE'));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/7964
     */
    public function testSourceLanguageFallback()
    {
        $translator = $this->factory->create([
            '__class' => Translator::class,
            'translations' => [
                '*' => [
                    '__class' => PhpMessageSource::class,
                    'basePath' => '@yii/tests/data/i18n/messages',
                    'sourceLanguage' => 'de-DE',
                    'fileMap' => [
                        'test' => 'test.php',
                        'foo' => 'test.php',
                    ],
                ],
            ],
        ]);

        $msg = 'The dog runs fast.';

        // source = target. Should be returned as is.
        $this->assertEquals($msg, $translator->translate('test', $msg, [], 'de-DE'));

        // target is less specific, than a source. Messages from sourceLanguage file should be loaded as a fallback
        $this->assertEquals('Der Hund rennt schnell.', $translator->translate('test', $msg, [], 'de'));
        $this->assertEquals('Hallo Welt!', $translator->translate('test', 'Hello world!', [], 'de'));

        // target is a different language than source
        $this->assertEquals('Собака бегает быстро.', $translator->translate('test', $msg, [], 'ru-RU'));
        $this->assertEquals('Собака бегает быстро.', $translator->translate('test', $msg, [], 'ru'));
    }

    public function testTranslateParams()
    {
        $msg = 'His speed is about {n} km/h.';
        $params = ['n' => 42];
        $this->assertEquals('His speed is about 42 km/h.', $this->translator->translate('test', $msg, $params, 'en-US'));
        $this->assertEquals('Seine Geschwindigkeit beträgt 42 km/h.', $this->translator->translate('test', $msg, $params, 'de-DE'));
    }

    public function testTranslateParams2()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('intl not installed. Skipping.');
        }
        $msg = 'His name is {name} and his speed is about {n, number} km/h.';
        $params = [
            'n' => 42,
            'name' => 'DA VINCI', // http://petrix.com/dognames/d.html
        ];
        $this->assertEquals('His name is DA VINCI and his speed is about 42 km/h.', $this->translator->translate('test', $msg, $params, 'en-US'));
        $this->assertEquals('Er heißt DA VINCI und ist 42 km/h schnell.', $this->translator->translate('test', $msg, $params, 'de-DE'));
    }

    public function testSpecialParams()
    {
        $msg = 'His speed is about {0} km/h.';

        $this->assertEquals('His speed is about 0 km/h.', $this->translator->translate('test', $msg, 0, 'en-US'));
        $this->assertEquals('His speed is about 42 km/h.', $this->translator->translate('test', $msg, 42, 'en-US'));
        $this->assertEquals('His speed is about {0} km/h.', $this->translator->translate('test', $msg, null, 'en-US'));
        $this->assertEquals('His speed is about {0} km/h.', $this->translator->translate('test', $msg, [], 'en-US'));
    }

    /**
     * When translation is missing source language should be used for formatting.
     *
     * @see https://github.com/yiisoft/yii2/issues/2209
     */
    public function testMissingTranslationFormatting()
    {
        $this->assertEquals('1 item', $this->translator->translate('test', '{0, number} {0, plural, one{item} other{items}}', 1, 'hu'));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/7093
     */
    public function testRussianPlurals()
    {
        $this->assertEquals('На диване лежит 6 кошек!', $this->translator->translate('test', 'There {n, plural, =0{no cats} =1{one cat} other{are # cats}} on lying on the sofa!', ['n' => 6], 'ru'));
    }

    public function testUsingSourceLanguageForMissingTranslation()
    {
        $this->i18n->setLocale('en');

        $msg = '{n, plural, =0{Нет комментариев} =1{# комментарий} one{# комментарий} few{# комментария} many{# комментариев} other{# комментария}}';
        $this->assertEquals('Нет комментариев', Yii::t('yii', $msg, ['n' => 0]));
        $this->assertEquals('1 комментарий', Yii::t('yii', $msg, ['n' => 1]));
        $this->assertEquals('2 комментария', Yii::t('yii', $msg, ['n' => 2]));
        $this->assertEquals('3 комментария', Yii::t('yii', $msg, ['n' => 3]));
        $this->assertEquals('4 комментария', Yii::t('yii', $msg, ['n' => 4]));
        $this->assertEquals('5 комментариев', Yii::t('yii', $msg, ['n' => 5]));
        $this->assertEquals('6 комментариев', Yii::t('yii', $msg, ['n' => 6]));
        $this->assertEquals('7 комментариев', Yii::t('yii', $msg, ['n' => 7]));
        $this->assertEquals('8 комментариев', Yii::t('yii', $msg, ['n' => 8]));
        $this->assertEquals('9 комментариев', Yii::t('yii', $msg, ['n' => 9]));
        $this->assertEquals('10 комментариев', Yii::t('yii', $msg, ['n' => 10]));
        $this->assertEquals('21 комментарий', Yii::t('yii', $msg, ['n' => 21]));
        $this->assertEquals('100 комментариев', Yii::t('yii', $msg, ['n' => 100]));
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/2519
     */
    public function testMissingTranslationEvent()
    {
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
        $this->assertEquals('Missing translation message.', $this->translator->translate('test', 'Missing translation message.', [], 'de-DE'));
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));

        Event::on(PhpMessageSource::class, TranslationEvent::MISSING, function ($event) {});
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
        $this->assertEquals('Missing translation message.', $this->translator->translate('test', 'Missing translation message.', [], 'de-DE'));
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
        Event::off(PhpMessageSource::class, TranslationEvent::MISSING);

        Event::on(PhpMessageSource::class, TranslationEvent::MISSING, function ($event) {
            if ($event->message === 'New missing translation message.') {
                $event->translatedMessage = 'TRANSLATION MISSING HERE!';
            }
        });
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
        $this->assertEquals('Another missing translation message.', $this->translator->translate('test', 'Another missing translation message.', [], 'de-DE'));
        $this->assertEquals('Missing translation message.', $this->translator->translate('test', 'Missing translation message.', [], 'de-DE'));
        $this->assertEquals('TRANSLATION MISSING HERE!', $this->translator->translate('test', 'New missing translation message.', [], 'de-DE'));
        $this->assertEquals('Hallo Welt!', $this->translator->translate('test', 'Hello world!', [], 'de-DE'));
        Event::off(PhpMessageSource::class, TranslationEvent::MISSING);
    }

    public function sourceLanguageDataProvider()
    {
        return [
            ['en-GB'],
            ['en'],
        ];
    }

    /**
     * @dataProvider sourceLanguageDataProvider
     * @param $sourceLanguage
     * TODO: FIXME
     */
    public function tmpoff_testIssue11429($sourceLanguage)
    {
        $this->destroyApplication();
        $this->mockApplication();
        $this->setTranslator();

        $this->app->sourceLanguage = $sourceLanguage;
        $logger = $this->app->getLogger();
        $logger->messages = [];
        $filter = function ($array) {
            // Ensures that error message is related to PhpMessageSource
            $className = $this->getMessageSourceClass();
            return substr_compare($array[2]['category'], $className, 0, strlen($className)) === 0;
        };

        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', 'The dog runs fast.', [], 'en-GB'));
        $this->assertEquals([], array_filter($logger->messages, $filter));

        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', 'The dog runs fast.', [], 'en'));
        $this->assertEquals([], array_filter($logger->messages, $filter));

        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', 'The dog runs fast.', [], 'en-CA'));
        $this->assertEquals([], array_filter($logger->messages, $filter));

        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', 'The dog runs fast.', [], 'hz-HZ'));
        $this->assertCount(1, array_filter($logger->messages, $filter));
        $logger->messages = [];

        $this->assertEquals('The dog runs fast.', $this->translator->translate('test', 'The dog runs fast.', [], 'hz'));
        $this->assertCount(1, array_filter($logger->messages, $filter));
        $logger->messages = [];
    }

    /**
     * Formatting a message that contains params but they are not provided.
     * @see https://github.com/yiisoft/yii2/issues/10884
     */
    public function testFormatMessageWithNoParam()
    {
        $message = 'Incorrect password (length must be from {min, number} to {max, number} symbols).';
        $expected = 'Incorrect password (length must be from {min} to {max} symbols).';
        $this->assertEquals($expected, $this->translator->format($message, ['attribute' => 'password'], 'en'));
    }
}