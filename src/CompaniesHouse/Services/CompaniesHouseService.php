<?php

namespace InnotecScotlandLtd\CompaniesHouse\Services;

class CompaniesHouseService
{
    protected $curl;
    protected $headers;

    public function __construct()
    {
        $this->curl = new CurlService();
        $this->headers = [
            'Accept: application/json',
            'Authorization: Basic '.base64_encode(config('companiesHouse.COMPANIES_HOUSE_API_KEY')),
        ];
    }

    /**
     * @param $companyNumber
     * @param string $responseType
     * @return array|null
     */
    public function get($companyNumber, $responseType = '')
    {
        $url = config('companiesHouse.COMPANIES_HOUSE_API').'/company/'.$companyNumber;
        if ($responseType === 'Mortgage') {
            $url = config('companiesHouse.COMPANIES_HOUSE').$companyNumber.'.json';
        }
        $ch = $this->curl->initiateCurl($url, [], $this->headers);
        $response = $this->curl->executeCurl($ch);
        $this->curl->closeCurl($ch);
        if (!empty($response)) {
            $response = json_decode($response, true);
            $returnResponse = [];
            switch ($responseType) {
                case 'CompanyName':
                    if (!empty($response['company_name'])) {
                        $returnResponse = $response['company_name'];
                    }
                    break;
                case 'CompanyStatus':
                    if (!empty($response['company_status'])) {
                        $returnResponse = $response['company_status'];
                    }
                    break;
                case 'SICCode':
                    if (!empty($response['sic_codes'])) {
                        $returnResponse = $response['sic_codes'];
                    }
                    break;
                case 'IncorporationDate':
                    if (!empty($response['date_of_creation'])) {
                        $returnResponse = $response['date_of_creation'];
                    }
                    break;
                case 'LastAccounts':
                    if (!empty($response['accounts']['last_accounts'])) {
                        $returnResponse = $response['accounts']['last_accounts'];
                    }
                    break;

                case 'Mortgage':
                    if (!empty($response['primaryTopic']['Mortgages'])) {
                        $returnResponse = $response['primaryTopic']['Mortgages']['NumMortCharges'];
                    }
                    break;
                default:
                    $returnResponse = $response;
                    break;
            }

            return $returnResponse;
        }

        return null;
    }

    public function getDocuments($companyNumber)
    {
        $url = config('companiesHouse.COMPANIES_HOUSE_API').'/company/'.$companyNumber.'/filing-history?items_per_page=10';
        $ch = $this->curl->initiateCurl($url, [], $this->headers);
        $response = $this->curl->executeCurl($ch);
        $this->curl->closeCurl($ch);
        if (!empty($response)) {
            return json_decode($response, true);
        }

        return null;
    }

    public function getCompanyAssets($id, $company_id)
    {
        $headers = [
            'Accept: application/xhtml+xml',
            'Authorization: Basic '.base64_encode(env('COMPANIES_HOUSE_API_KEY')),
        ];
        $url = config('companiesHouse.COMPANIES_HOUSE_DOCUMENT').'/company/'.$company_id.'/filing-history/'.$id.'/document?format=xhtml';
        $ch = $this->curl->initiateCurl($url, [], $headers);
        $response = $this->curl->executeCurl($ch);
        if (!empty($response) && stripos($response, '<?xml') === false) {
            $url = config('companiesHouse.COMPANIES_HOUSE_DOCUMENT').'/document/'.$id.'/content';
            $ch = $this->curl->initiateCurl($url, [], $headers);
            $response = $this->curl->executeCurl($ch);
        }

        $fixed_asset = 0;
        $current_asset = 0;
        $liabilities = 0;
        $response = trim($response);
        $date = null;
        $refName = '';

        try {
            if (!empty($response) && stripos($response, '<?xml') !== false) {
                $dom = new \DOMDocument();
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                $dom->loadXML($response);

                $xpath = new \DOMXPath($dom);

                $xpath->registerNamespace("xbrli", "http://www.xbrl.org/2003/instance");

                $header = $xpath->query('//ix:header//ix:hidden');

                foreach ($header as $nodeList) {
                    foreach ($nodeList->childNodes as $childNode) {
                        if ($date) break;

                        foreach ($childNode->attributes as $attribute) {
                            if ($attribute->nodeName == 'contextRef') {
                                $refName = $attribute->nodeValue;
                            }

                            if ($attribute->nodeName == 'name'
                                && strpos($attribute->nodeValue, ':EndDateForPeriodCoveredByReport') !== false) {
                                $date = $childNode->nodeValue;

                                break;
                            }
                        }
                    }
                }

                $nodeList = $xpath->query('//xbrli:context[@id="'.$refName.'"]//xbrli:instant');

                foreach ($nodeList as $item) {
                    $date = $item->nodeValue;
                }

                $assets = $xpath->query('//ix:nonFraction[@contextRef="'.$refName.'"]');

                foreach ($assets as $asset) {
                    $name = $asset->getAttribute('name');

                    if (strpos($name, ':FixedAssets') !== false && $fixed_asset === 0) {
                        $fixed_asset = $this->getAssetValue($asset);
                    } elseif (strpos($name, ':CurrentAssets') !== false && $current_asset === 0) {
                        $current_asset = $this->getAssetValue($asset);
                    } elseif (strpos($name, ':NetCurrentAssetsLiabilities') !== false && $liabilities === 0) {
                        $liabilities = $this->getAssetValue($asset);
                    }
                }

                if ($fixed_asset === 0 && $current_asset === 0 && $liabilities === 0) {
                    $assets = $xpath->query('//ix:nonFraction');

                    foreach ($assets as $asset) {
                        $name = $asset->getAttribute('name');

                        if (strpos($name, ':FixedAssets') !== false && $fixed_asset === 0) {
                            $fixed_asset = $this->getAssetValue($asset);
                        } elseif (strpos($name, ':CurrentAssets') !== false && $current_asset === 0) {
                            $current_asset = $this->getAssetValue($asset);
                        } elseif (strpos($name, ':NetCurrentAssetsLiabilities') !== false && $liabilities === 0) {
                            $liabilities = $this->getAssetValue($asset);
                        }
                    }
                }

                $this->curl->closeCurl($ch);
            }
        } catch (\Exception $e) {
            return false;
        }

        return [
            'fixed_asset'   => $fixed_asset,
            'current_asset' => $current_asset,
            'liabilities'   => $liabilities,
            'date'          => $date,
        ];
    }

    public function getOfficers($companyNumber)
    {
        $url = config('companiesHouse.COMPANIES_HOUSE_API').'/company/'.$companyNumber.'/officers';
        $ch = $this->curl->initiateCurl($url, [], $this->headers);
        $response = $this->curl->executeCurl($ch);

        $this->curl->closeCurl($ch);

        return json_decode($response);
    }

    public function getAssetValue($asset)
    {
        $value = floatval(str_replace(',', '', $asset->nodeValue));

        if ($asset->hasAttribute('sign') && $asset->getAttribute('sign') == '-') {
            $value = $value * (-1);
        }

        return $value;
    }
}
