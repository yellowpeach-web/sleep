<?php 

    class LayoutOptions {

        public $contentSections = [
            'appBanner' => true,
            'appButtons' => true,
            'breadcrumbNav' => true,
            'cookieBanner' => true,
            'footer' => true,
            'header' => true,
            'sidebar' => true,
            'storeBanner' => true,
            'subNav' => true
        ];

        private $queryParam = 'layoutOptions';

        function __construct() {
            $this->prepareLayoutOptions();
        }

        /**
         * Check if content section is visible
         * @return boolean
         */
        public function isVisible($section) {
            return $this->contentSections[$section];
        }

        /**
         * Toggle off sections for partner webview group 
         */
        private function togglePartnerWebview() {
            
            $hiddenSections = [            
                'appBanner',
                'appButtons',
                'subNav',
                'breadcrumbNav',
                'cookieBanner',
                'footer',
                'header',
                'sidebar',
                'storeBanner'
            ];

            foreach($hiddenSections as $section) {
                $this->contentSections[$section] = false;
            }

        }

        /**
         * Prepare content section visibility
         */
        private function prepareLayoutOptions() {

            // check if the parameter is set
            if (!isset($_GET[$this->queryParam])) {
                return;
            }

            // base64 decode the value
            $value = base64_decode($_GET[$this->queryParam]);

            // separate values
            $options = explode(',', $value);

            // get the key value pairs
            $params = [];
            foreach($options as $opt) {
                $p = explode('=', $opt);
                $params[$p[0]] = $p[1];
            }

            // update content section visibility
            foreach($params as $key => $value) {
                if (array_key_exists($key, $this->contentSections) && $value == 'off') {
                    $this->contentSections[$key] = false;
                }  
            }

            if (isset($params['partnerWebview']) && $params['partnerWebview'] == 'on') {
                $this->togglePartnerWebview();
            }


        }

    }

?>