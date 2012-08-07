<?php
/**
 * @author      Gordon Knoppe
 * @category    Guidance
 * @package     Cachebuster
 * @copyright   Copyright (c) 2012 Guidance Solutions (http://www.guidance.com)
 * @license
 */

class Guidance_Cachebuster_Model_Observer
{
    /** @var array */
    protected $_find = array();

    /**
     * Parse html from all rendered blocks for links to be replaced
     *
     * @param Varien_Event_Observer $observer
     */
    public function core_block_abstract_to_html_after(Varien_Event_Observer $observer)
    {
        $transport = $observer->getEvent()->getTransport();
        $html      = $transport->getHtml();

        // Find all urls in html
        $urls = $this->_scrapeUrls($html);

        // Loop all urls
        foreach ($urls as $url) {
            if (strpos($url, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS)) !== FALSE) {
                $this->_addUrlToProcess(
                    $url,
                    $this->_addTimestampToUrl(
                        $url,
                        Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS),
                        Mage::getBaseDir() . '/js/'
                    )
                );
            } elseif (strpos($url, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA)) !== FALSE) {
                $this->_addUrlToProcess(
                    $url,
                    $this->_addTimestampToUrl(
                        $url,
                        Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA),
                        Mage::getBaseDir() . '/media/'
                    )
                );
            } elseif (strpos($url, Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN)) !== FALSE) {
                $this->_addUrlToProcess(
                    $url,
                    $this->_addTimestampToUrl(
                        $url,
                        Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_SKIN),
                        Mage::getBaseDir() . '/skin/'
                    )
                );
            }
        }
    }

    /**
     * Process response by performing a search and replace on urls to be processed
     *
     * @param Varien_Event_Observer $observer
     */
    public function controller_action_postdispatch(Varien_Event_Observer $observer)
    {
        if (count($this->_find)) {
            $response = $observer->getControllerAction()->getResponse();
            $body = $response->getBody();
            $body = str_replace(array_keys($this->_find), array_values($this->_find), $body);
            $response->setBody($body);
        }
    }

    /**
     * Scrapes urls from given html using regex from:
     *
     * https://gist.github.com/249502/
     * http://stackoverflow.com/questions/2026041/help-hacking-grubers-liberal-url-regex
     *
     * @param $html
     * @return array
     */
    protected function _scrapeUrls($html)
    {
        $urls    = array();
        $matches = array();
        $regex   = <<<REGEX
\b((?:[a-z][\w-]+:(?:\/{1,3}|[a-z0-9%])|www\d{0,3}[.])(?:[^\s()<>]+|\([^\s()<>]+\))+(?:\([^\s()<>]+\)|[^`!()\[\]{};:'".,<>?«»“”‘’\s]))
REGEX;

        preg_match_all("/$regex/", $html, $matches);

        if (isset($matches[0]) && is_array($matches[0]) && count($matches[0])) {
            $urls = $matches[0];
        }

        return $urls;
    }

    /**
     * Add timestamp to the filename portion of URL using $basePath to determine timestamp for URL
     *
     * @param $url
     * @param $baseUrl
     * @param $basePath
     * @return mixed|string
     */
    protected function _addTimestampToUrl($url, $baseUrl, $basePath)
    {
        $url = $this->_sanitizeUrl($url);
        $baseUrl = $this->_sanitizeUrl($baseUrl);

        $path = str_replace($baseUrl, $basePath, $url);
        $pathinfo = pathinfo($path);

        if (empty($pathinfo['extension']) || empty($pathinfo['filename']) || empty($pathinfo['basename'])
            || !file_exists($path)
        ) {
            return $url;
        }

        $timestamp = filemtime($path);

        $final = array(
            $pathinfo['filename'],
            $timestamp,
            $pathinfo['extension'],
        );

        return str_replace($pathinfo['basename'], implode('.', $final), $url);
    }

    /**
     * Sanitize URL by removing query, fragment, user, or pass if found
     *
     * @param $url
     * @return string
     */
    protected function _sanitizeUrl($url)
    {
        $url    = parse_url($url);
        $scheme = isset($url['scheme']) ? $url['scheme'] . '://' : '';
        $host   = isset($url['host']) ? $url['host'] : '';
        $port   = isset($url['port']) ? ':' . $url['port'] : '';
        $path   = isset($url['path']) ? $url['path'] : '';
        return "$scheme$host$port$path";
    }

    protected function _addUrlToProcess($find, $replace)
    {
        if ($find != $replace) {
            $this->_find[$find] = $replace;
        }
        return $this;
    }

}