<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Spryker Marketplace License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\PriceProductOfferGui\Communication\Reader;

use Generated\Shared\Transfer\PriceProductTransfer;
use Generated\Shared\Transfer\ProductOfferTransfer;
use Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceFacadeInterface;
use Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceProductFacadeInterface;
use Spryker\Zed\PriceProductOfferGui\Dependency\Service\PriceProductOfferGuiToUtilEncodingServiceInterface;

class PriceProductOfferReader implements PriceProductOfferReaderInterface
{
    /**
     * @var \Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceProductFacadeInterface
     */
    protected $priceProductFacade;

    /**
     * @var \Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceFacadeInterface
     */
    protected $priceFacade;

    /**
     * @var \Spryker\Zed\PriceProductOfferGui\Dependency\Service\PriceProductOfferGuiToUtilEncodingServiceInterface
     */
    protected $utilEncodingService;

    /**
     * @var string
     */
    protected static $netPriceModeIdentifier;

    /**
     * @var string
     */
    protected static $grossPriceModeIdentifier;

    /**
     * @param \Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceProductFacadeInterface $priceProductFacade
     * @param \Spryker\Zed\PriceProductOfferGui\Dependency\Facade\PriceProductOfferGuiToPriceFacadeInterface $priceFacade
     * @param \Spryker\Zed\PriceProductOfferGui\Dependency\Service\PriceProductOfferGuiToUtilEncodingServiceInterface $utilEncodingService
     */
    public function __construct(
        PriceProductOfferGuiToPriceProductFacadeInterface $priceProductFacade,
        PriceProductOfferGuiToPriceFacadeInterface $priceFacade,
        PriceProductOfferGuiToUtilEncodingServiceInterface $utilEncodingService
    ) {
        $this->priceProductFacade = $priceProductFacade;
        $this->priceFacade = $priceFacade;
        $this->utilEncodingService = $utilEncodingService;
    }

    /**
     * @phpstan-return array<string, mixed>
     *
     * @param \Generated\Shared\Transfer\ProductOfferTransfer $productOfferTransfer
     *
     * @return array
     */
    public function getProductOfferPricesData(ProductOfferTransfer $productOfferTransfer): array
    {
        if (!$productOfferTransfer->getPrices()->count()) {
            return [];
        }

        $priceTable = [];
        $currencies = [];
        $priceData = [];
        $priceDataByPriceType = [];

        foreach ($productOfferTransfer->getPrices() as $priceProductTransfer) {
            $priceTable = $this->getPriceTable($priceProductTransfer, $priceTable);
            $priceData = $this->getPriceData($priceProductTransfer, $priceData);
            $currencyTransfer = $priceProductTransfer->getMoneyValue()->getCurrency();
            $currencies[$currencyTransfer->getCode()] = $currencyTransfer;
            $priceDataByPriceType = $this->getPriceDataByPriceType($priceProductTransfer, $priceDataByPriceType);
        }

        return [
            'priceTable' => $priceTable,
            'currencies' => $currencies,
            'priceData' => $priceData,
            'productOffer' => $productOfferTransfer,
            'priceDataByPriceType' => $priceDataByPriceType,
        ];
    }

    /**
     * @phpstan-param array<string, \Generated\Shared\Transfer\PriceProductTransfer> $priceTable
     *
     * @phpstan-return array<string, \Generated\Shared\Transfer\PriceProductTransfer>
     *
     * @param \Generated\Shared\Transfer\PriceProductTransfer $priceProductTransfer
     * @param array $priceTable
     *
     * @return array
     */
    protected function getPriceTable(
        PriceProductTransfer $priceProductTransfer,
        array $priceTable
    ): array {
        $priceTypeTransfer = $priceProductTransfer->getPriceType();

        if (!$priceTypeTransfer) {
            return $priceTable;
        }

        $grossPriceModeIdentifier = $this->getGrossPriceModeIdentifier();
        $netPriceModeIdentifier = $this->getNetPriceModeIdentifier();

        $priceType = $priceTypeTransfer->getName();
        $priceModeConfiguration = $priceTypeTransfer->getPriceModeConfiguration();

        $storeName = $priceProductTransfer->getMoneyValue()->getStore()->getName();
        $currencyIsoCode = $priceProductTransfer->getMoneyValue()->getCurrency()->getCode();

        if ($priceModeConfiguration === $this->getPriceModeIdentifierForBothType()) {
            $priceTable[$storeName][$currencyIsoCode][$netPriceModeIdentifier][$priceType] = $priceProductTransfer;
            $priceTable[$storeName][$currencyIsoCode][$grossPriceModeIdentifier][$priceType] = $priceProductTransfer;

            return $priceTable;
        }

        $priceTable[$storeName][$currencyIsoCode][$priceModeConfiguration][$priceType] = $priceProductTransfer;

        return $priceTable;
    }

    /**
     * @phpstan-param array<string, mixed> $priceData
     *
     * @phpstan-return array<string, mixed>
     *
     * @param \Generated\Shared\Transfer\PriceProductTransfer $priceProductTransfer
     * @param array $priceData
     *
     * @return array
     */
    protected function getPriceData(
        PriceProductTransfer $priceProductTransfer,
        array $priceData
    ): array {
        $storeName = $priceProductTransfer->getMoneyValue()->getStore()->getName();
        $currencyIsoCode = $priceProductTransfer->getMoneyValue()->getCurrency()->getCode();

        $priceData[$storeName][$currencyIsoCode] = $this->getDecodedPriceData($priceProductTransfer);

        return $priceData;
    }

    /**
     * @return string
     */
    protected function getPriceModeIdentifierForBothType(): string
    {
        return $this->priceProductFacade->getPriceModeIdentifierForBothType();
    }

    /**
     * @return string
     */
    protected function getNetPriceModeIdentifier(): string
    {
        if (!static::$netPriceModeIdentifier) {
            static::$netPriceModeIdentifier = $this->priceFacade->getNetPriceModeIdentifier();
        }

        return static::$netPriceModeIdentifier;
    }

    /**
     * @return string
     */
    protected function getGrossPriceModeIdentifier(): string
    {
        if (!static::$grossPriceModeIdentifier) {
            static::$grossPriceModeIdentifier = $this->priceFacade->getGrossPriceModeIdentifier();
        }

        return static::$grossPriceModeIdentifier;
    }

    /**
     * @phpstan-param array<string, mixed> $priceDataByPriceType
     *
     * @phpstan-return array<string, mixed>
     *
     * @param \Generated\Shared\Transfer\PriceProductTransfer $priceProductTransfer
     * @param array $priceDataByPriceType
     *
     * @return array
     */
    protected function getPriceDataByPriceType(
        PriceProductTransfer $priceProductTransfer,
        array $priceDataByPriceType
    ): array {
        $storeName = $priceProductTransfer->getMoneyValue()->getStore()->getName();
        $currencyIsoCode = $priceProductTransfer->getMoneyValue()->getCurrency()->getCode();
        $priceTypeName = $priceProductTransfer->getPriceType()->getName();

        $priceDataByPriceType[$storeName][$currencyIsoCode][$priceTypeName] = $this->getDecodedPriceData($priceProductTransfer);

        return $priceDataByPriceType;
    }

    /**
     * @phpstan-return array<string, mixed>
     *
     * @param \Generated\Shared\Transfer\PriceProductTransfer $priceProductTransfer
     *
     * @return array
     */
    protected function getDecodedPriceData(PriceProductTransfer $priceProductTransfer): array
    {
        return $this->utilEncodingService
            ->decodeJson($priceProductTransfer->getMoneyValue()->getPriceData(), true) ?? [];
    }
}
