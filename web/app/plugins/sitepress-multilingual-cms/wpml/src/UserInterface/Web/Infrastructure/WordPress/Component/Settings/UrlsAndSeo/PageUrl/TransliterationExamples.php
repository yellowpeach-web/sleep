<?php

namespace WPML\UserInterface\Web\Infrastructure\WordPress\Component\Settings\UrlsAndSeo\PageUrl;

class TransliterationExamples {

  private const EXAMPLES = [
    'ar'      => [ 'label' => 'Arabic',                'nativeTitle' => 'تفعيل WPML',          'pageUrl' => '/ar/tfyl-wpml/' ],
    'hy'      => [ 'label' => 'Armenian',              'nativeTitle' => 'WPML-ի ակտիվացում',   'pageUrl' => '/hy/wpml-aktivacum/' ],
    'bn'      => [ 'label' => 'Bengali',               'nativeTitle' => 'WPML অ্যাক্টিভেশন',     'pageUrl' => '/bn/wpml-sokriyokoron/' ],
    'bg'      => [ 'label' => 'Bulgarian',             'nativeTitle' => 'Активиране на WPML',   'pageUrl' => '/bg/wpml-aktivatsiya/' ],
    'zh-hans' => [ 'label' => 'Chinese (Simplified)',  'nativeTitle' => 'WPML 激活',            'pageUrl' => '/zh-hans/wpml-jihuo/' ],
    'zh-hant' => [ 'label' => 'Chinese (Traditional)', 'nativeTitle' => 'WPML 啟用',            'pageUrl' => '/zh-hant/wpml-qiyong/' ],
    'el'      => [ 'label' => 'Greek',                 'nativeTitle' => 'Ενεργοποίηση WPML',    'pageUrl' => '/el/energopoiisi-wpml/' ],
    'he'      => [ 'label' => 'Hebrew',                'nativeTitle' => 'הפעלת WPML',           'pageUrl' => '/he/hafaalat-wpml/' ],
    'hi'      => [ 'label' => 'Hindi',                 'nativeTitle' => 'WPML सक्रियण',          'pageUrl' => '/hi/wpml-sakriyan/' ],
    'ja'      => [ 'label' => 'Japanese',              'nativeTitle' => 'WPMLの有効化',          'pageUrl' => '/ja/wpml-no-youkouka/' ],
    'ko'      => [ 'label' => 'Korean',                'nativeTitle' => 'WPML 활성화',           'pageUrl' => '/ko/wpml-hwal-seong-hwa/' ],
    'ku'      => [ 'label' => 'Kurdish',               'nativeTitle' => 'چالاککردنی WPML',      'pageUrl' => '/ku/chalakkirdini-wpml/' ],
    'mk'      => [ 'label' => 'Macedonian',            'nativeTitle' => 'Активација на WPML',   'pageUrl' => '/mk/wpml-aktivacija/' ],
    'mn'      => [ 'label' => 'Mongolian',             'nativeTitle' => 'WPML идэвхжүүлэлт',     'pageUrl' => '/mn/wpml-idevkhzhuulelt/' ],
    'ne'      => [ 'label' => 'Nepali',                'nativeTitle' => 'WPML सक्रियकरण',        'pageUrl' => '/ne/wpml-sakriyata/' ],
    'fa'      => [ 'label' => 'Persian',               'nativeTitle' => 'فعال‌سازی WPML',       'pageUrl' => '/fa/flszy-wpml/' ],
    'pa'      => [ 'label' => 'Punjabi',               'nativeTitle' => 'WPML ਐਕਟੀਵੇਸ਼ਨ',        'pageUrl' => '/pa/wpml-aikttiiveesn/' ],
    'ru'      => [ 'label' => 'Russian',               'nativeTitle' => 'Активация WPML',        'pageUrl' => '/ru/aktivatsiia-wpml/' ],
    'sr'      => [ 'label' => 'Serbian',               'nativeTitle' => 'WPML активација',       'pageUrl' => '/sr/wpml-aktivacija/' ],
    'ta'      => [ 'label' => 'Tamil',                 'nativeTitle' => 'WPML செயலாக்கம்',         'pageUrl' => '/ta/wpml-seyalaakkam/' ],
    'th'      => [ 'label' => 'Thai',                  'nativeTitle' => 'การเปิดใช้งาน WPML',       'pageUrl' => '/th/kan-poet-chai-ngan-wpml/' ],
    'uk'      => [ 'label' => 'Ukrainian',             'nativeTitle' => 'Активація WPML',        'pageUrl' => '/uk/aktyvatsiia-wpml/' ],
    'ur'      => [ 'label' => 'Urdu',                  'nativeTitle' => 'WPML کی فعالیت',        'pageUrl' => '/ur/wpml-ki-faaliyat/' ],
    'yi'      => [ 'label' => 'Yiddish',               'nativeTitle' => 'WPML אַקטיוואַציע',        'pageUrl' => '/yi/wpml-aqtyvvatsy/' ],
  ];


  public static function get( string $code ): ?array {
    return self::EXAMPLES[ $code ] ?? null;
  }


}
