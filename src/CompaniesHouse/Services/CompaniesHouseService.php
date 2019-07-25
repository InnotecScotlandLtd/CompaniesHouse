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

    public function getCompanyAssets($id)
    {
        $headers = [
            'Accept: application/xhtml+xml',
            'Authorization: Basic '.base64_encode(env('COMPANIES_HOUSE_API_KEY')),
        ];
        $url = config('companiesHouse.COMPANIES_HOUSE_DOCUMENT_API').'/document/'.$id.'/content';
        $ch = $this->curl->initiateCurl($url, [], $headers);
        $response = $this->curl->executeCurl($ch);

        $fixed_asset = 0;
        $current_asset = 0;
        $liabilities = 0;
        $response = trim($response);

        if (!empty($response) && stripos($response, '<?xml') !== false) {
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($response);
            $assets = $dom->getElementsByTagName('td');

            if (!empty($assets)) {
                foreach ($assets as $asset) {
                    foreach ($asset->childNodes as $item) {
                        if (!empty($item->attributes)) {
                            $name = $item->getAttribute('name');
                            if ($name === 'ns5:FixedAssets' && $fixed_asset === 0) {
                                $fixed_asset = $asset->nodeValue;
                            }
                            if ($name === 'ns5:CurrentAssets' && $current_asset === 0) {
                                $current_asset = $asset->nodeValue;
                            }
                            if ($name === 'ns5:NetAssetsLiabilities' && $liabilities === 0) {
                                $liabilities = str_replace(['(', ')'], ['', ''], $asset->nodeValue);
                            }
                        }
                    }
                }
            }
            $this->curl->closeCurl($ch);
        }

        return ['fixed_asset' => $fixed_asset, 'current_asset' => $current_asset, 'liabilities' => $liabilities];
    }


}