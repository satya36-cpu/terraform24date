<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2022 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\ViewModel;

use Exception;
use Extend\Warranty\Helper\Api\Data as DataHelper;
use Extend\Warranty\Helper\Data as ExtendHelper;
use Extend\Warranty\Model\Api\Response\LeadInfoResponse;
use Extend\Warranty\Model\LeadInfo;
use Extend\Warranty\Model\WarrantyRelation;
use Extend\Warranty\Helper\Tracking as TrackingHelper;
use Extend\Warranty\Model\Offers as OfferModel;
use Extend\Warranty\Model\Config\Source\ProductPagePlacement;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\ConfigurableProduct\Api\LinkManagementInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\Data\OrderItemSearchResultInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Warranty
 *
 * Warranty ViewModel
 */
class Warranty implements ArgumentInterface
{
    /**
     * Data Helper Model
     *
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * Json Serializer Model
     *
     * @var JsonSerializer
     */
    private $jsonSerializer;

    /**
     * Link Management Interface
     *
     * @var LinkManagementInterface
     */
    private $linkManagement;

    /**
     * Warranty Tracking Helper
     *
     * @var TrackingHelper
     */
    private $trackingHelper;

    /**
     * Offer
     *
     * @var OfferModel
     */
    private $offerModel;

    /**
     * Checkout Session Model
     *
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Request Model
     *
     * @var Http
     */
    private $request;

    /**
     * Order Item Repository Model
     *
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * Search Criteria Builder Model
     *
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var AdminSession
     */
    private $adminSession;

    /**
     * @var LeadInfo
     */
    private $leadInfo;

    protected $warrantyRelation;

    protected $helper;

    /**
     * Warranty constructor
     *
     * @param DataHelper $dataHelper
     * @param JsonSerializer $jsonSerializer
     * @param LinkManagementInterface $linkManagement
     * @param TrackingHelper $trackingHelper
     * @param OfferModel $offerModel
     * @param CheckoutSession $checkoutSession
     * @param Http $request
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param AdminSession $adminSession
     * @param LeadInfo $leadInfo
     * @param WarrantyRelation $warrantyRelation
     */
    public function __construct(
        DataHelper                   $dataHelper,
        JsonSerializer               $jsonSerializer,
        LinkManagementInterface      $linkManagement,
        TrackingHelper               $trackingHelper,
        OfferModel                   $offerModel,
        CheckoutSession              $checkoutSession,
        Http                         $request,
        OrderItemRepositoryInterface $orderItemRepository,
        SearchCriteriaBuilder        $searchCriteriaBuilder,
        StoreManagerInterface        $storeManager,
        AdminSession                 $adminSession,
        LeadInfo                     $leadInfo,
        WarrantyRelation             $warrantyRelation,
        ExtendHelper                 $helper
    )
    {
        $this->dataHelper = $dataHelper;
        $this->helper = $helper;
        $this->jsonSerializer = $jsonSerializer;
        $this->linkManagement = $linkManagement;
        $this->trackingHelper = $trackingHelper;
        $this->offerModel = $offerModel;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->orderItemRepository = $orderItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->storeManager = $storeManager;
        $this->adminSession = $adminSession;
        $this->leadInfo = $leadInfo;
        $this->warrantyRelation = $warrantyRelation;
    }

    /**
     * Check if module enabled
     * @param null|int $storeId
     *
     * @return bool
     */
    public function isExtendEnabled(int $storeId = null): bool
    {
        $result = false;

        if ($storeId) {
            return $this->dataHelper->isExtendEnabled(ScopeInterface::SCOPE_STORES, $storeId);
        }

        if ($this->isAdmin()) {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $result = $this->dataHelper->isExtendEnabled(ScopeInterface::SCOPE_STORES, $store->getId());
                if ($result) {
                    break;
                }
            }
        } else {
            $storeId = $this->storeManager->getStore()->getId();
            $result = $this->dataHelper->isExtendEnabled(ScopeInterface::SCOPE_STORES, $storeId);
        }

        return $result;
    }

    /**
     * Check if has warranty in cart by itemId
     *
     * @param CartInterface $quote
     * @param int $id
     * @return bool
     */
    public function hasWarranty(CartInterface $quote, int $id): bool
    {
        $checkQuoteItem = $quote->getItemById($id);
        return $checkQuoteItem && $this->warrantyRelation->quoteItemHasWarranty($checkQuoteItem);
    }

    /**
     * Check if shopping cart offers enabled
     *
     * @return bool
     */
    public function isShoppingCartOffersEnabled(): bool
    {
        $result = false;
        if ($this->isAdmin()) {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $result = $this->dataHelper->isShoppingAdminOffersEnabled($store->getId());
                if ($result) {
                    break;
                }
            }
        } else {
            $storeId = $this->storeManager->getStore()->getId();
            $result = $this->dataHelper->isShoppingCartOffersEnabled($storeId);
        }

        return $result;
    }

    /**
     * Check if product detail page offers enabled
     *
     * @return bool
     */
    public function isProductDetailPageOffersEnabled(): bool
    {
        return $this->dataHelper->isProductDetailPageOffersEnabled();
    }

    /**
     * Get PDP Offers Button placement (insertion point and logic)
     *
     * @param bool $isSimpleProduct
     * @return array
     */
    public function getProductDetailPageOffersPlacement(bool $isSimpleProduct): array
    {
        $pdpDisplay = $this->dataHelper->getProductDetailPageOffersPlacement();
        $placement = [
            'insertionPoint' => null,
            'insertionLogic' => null
        ];
        $logicBefore = 'before';
        $logicAfter = 'after';

        switch ($pdpDisplay) {
            case ProductPagePlacement::ACTIONS_BEFORE:
            case ProductPagePlacement::ACTIONS_AFTER:
                $placement['insertionPoint'] = 'div.actions';
                $placement['insertionLogic'] = $pdpDisplay === ProductPagePlacement::ACTIONS_BEFORE ? $logicBefore : $logicAfter;
                break;
            case ProductPagePlacement::ADD_TO_CART_BEFORE:
            case ProductPagePlacement::ADD_TO_CART_AFTER:
                $placement['insertionPoint'] = 'button.tocart';
                $placement['insertionLogic'] = $pdpDisplay === ProductPagePlacement::ADD_TO_CART_BEFORE ? $logicBefore : $logicAfter;
                break;
            case ProductPagePlacement::QUANTITY_BEFORE:
            case ProductPagePlacement::QUANTITY_AFTER:
                $placement['insertionPoint'] = 'div.field.qty';
                $placement['insertionLogic'] = $pdpDisplay === ProductPagePlacement::QUANTITY_BEFORE ? $logicBefore : $logicAfter;
                break;
            case ProductPagePlacement::OPTIONS_BEFORE:
            case ProductPagePlacement::OPTIONS_AFTER:
                if ($isSimpleProduct) {
                    $placement['insertionPoint'] = 'div.box-tocart .fieldset:first-child';
                    $placement['insertionLogic'] = $logicBefore;
                } else {
                    $placement['insertionPoint'] = 'div.product-options-wrapper';
                    $placement['insertionLogic'] = $pdpDisplay === ProductPagePlacement::OPTIONS_BEFORE ? $logicBefore : $logicAfter;
                }
                break;
            case ProductPagePlacement::SOCIAL_BEFORE:
            case ProductPagePlacement::SOCIAL_AFTER:
                $placement['insertionPoint'] = 'div.product-social-links';
                $placement['insertionLogic'] = $pdpDisplay === ProductPagePlacement::SOCIAL_BEFORE ? $logicBefore : $logicAfter;
                break;
        }

        return $placement;
    }

    /**
     * Check if products list offers enabled
     *
     * @return bool
     */
    public function isProductsListOffersEnabled(): bool
    {
        return $this->dataHelper->isProductsListOffersEnabled();
    }

    /**
     * Check if interstitial cart offers enabled
     *
     * @return bool
     */
    public function isInterstitialCartOffersEnabled(): bool
    {
        return $this->dataHelper->isInterstitialCartOffersEnabled();
    }


    /**
     * Check if product has warranty offers
     *
     * @param ProductInterface $product
     * @return string
     * @thrown InvalidArgumentException
     */
    public function isProductHasOffers(ProductInterface $product): string
    {
        $isProductHasOffers = [];
        $productSku = $product->getSku();

        if ($product->getTypeId() == Configurable::TYPE_CODE) {
            $items = $this->linkManagement->getChildren($productSku);
            foreach ($items as $item) {
                $itemSku = $item->getSku();
                $isProductHasOffers[$itemSku] = $this->offerModel->hasOffers($itemSku);
            }
        } else {
            $isProductHasOffers[$productSku] = $this->offerModel->hasOffers($productSku);
        }

        return $this->jsonSerializer->serialize($isProductHasOffers);
    }

    /**
     * Check if tracking enabled
     *
     * @return bool
     */
    public function isTrackingEnabled(): bool
    {
        return $this->trackingHelper->isTrackingEnabled();
    }

    /**
     * Check is leads enabled
     *
     * @return bool
     */
    public function isLeadEnabled(): bool
    {
        $result = false;
        if ($this->isAdmin()) {
            $stores = $this->storeManager->getStores();
            foreach ($stores as $store) {
                $result = $this->dataHelper->isShoppingAdminOffersEnabled($store->getId());
                if ($result) {
                    break;
                }
            }
        } else {
            $storeId = $this->storeManager->getStore()->getId();
            $result = $this->dataHelper->isLeadEnabled($storeId);
        }

        return $result;
    }

    /**
     * Check does quote have warranty item for the item
     *
     * @param OrderItemInterface $id
     * @return bool
     */
    public function itemHasLeadWarrantyInQuote($orderItem): bool
    {
        /** @var CartItemInterface $item */
        $relationSku = $this->warrantyRelation->getOfferOrderItemSku($orderItem);
        return !empty($this->warrantyRelation->getWarrantyByRelationSku($relationSku));
    }
    
    /**
     * Check does quote have warranty item for the item
     * Kept for backwards compatibility with Hyva module
     *
     * @param string $sku
     * @return bool
     */
    public function isWarrantyInQuote(string $sku): bool
    {
        try {
            $quote = $this->checkoutSession->getQuote();
        } catch (LocalizedException $exception) {
            $quote = null;
        }
        if ($quote) {
            $hasWarranty = $this->hasWarranty($quote, $sku);
        }
        return $hasWarranty ?? false;
    }

    /**
     * Check does later orders have warranty item for the item
     *
     * @param OrderItemInterface $item
     * @return bool
     */
    public function isWarrantyInLaterOrders(Item $item): bool
    {
        $isWarrantyInLaterOrders = false;
        $leadToken = $item->getLeadToken();
        $createdAt = $item->getCreatedAt();

        if (!empty($leadToken)) {
            $orderItems = $this->getOrderItemsByLeadToken($leadToken, $createdAt);

            if (count($orderItems) > 0) {
                $isWarrantyInLaterOrders = true;
            }
        }

        return $isWarrantyInLaterOrders;
    }

    /**
     * Get order items created later than the current by lead token
     *
     * @param string $leadToken
     * @param string $createdAt
     *
     * @return OrderItemSearchResultInterface
     */
    private function getOrderItemsByLeadToken(string $leadToken, string $createdAt)
    {
        $this->searchCriteriaBuilder->addFilter(
            'lead_token',
            $leadToken,
            'eq'
        );
        $this->searchCriteriaBuilder->addFilter(
            'created_at',
            $createdAt,
            'gt'
        );
        $searchCriteria = $this->searchCriteriaBuilder->create();

        return $this->orderItemRepository->getList($searchCriteria);
    }

    /**
     * Check is post purchase lead modal enabled
     *
     * @return bool
     */
    public function isPostPurchaseLeadModalEnabled(): bool
    {
        return $this->dataHelper->isLeadsModalEnabled();
    }

    /**
     * Check is warranty information order offers enabled
     *
     * @param $storeId
     * @return bool
     */
    public function isOrderOffersEnabled($storeId = null): bool
    {
        return $this->dataHelper->isOrderOffersEnabled($storeId);
    }

    /**
     * Get Lead Token From Url
     *
     * @return string
     */
    public function getLeadTokenFromUrl(): string
    {
        return $this->request->getParam(DataHelper::LEAD_TOKEN_URL_PARAM) ?? '';
    }

    /**
     * Decode data
     *
     * @param string|null $data
     *
     * @return array|null
     */
    public function unserialize($data)
    {
        try {
            $result = $this->jsonSerializer->unserialize($data);
        } catch (Exception $exception) {
            $result = null;
        }

        return $result;
    }

    /**
     * Get Lead Token
     *
     * @param OrderItemInterface $item
     * @return array
     */
    public function getLeadToken(OrderItemInterface $item)
    {
        return $this->unserialize($item->getLeadToken()) ?? [];
    }

    /**
     * @return bool
     */
    private function isAdmin()
    {
        return (bool)$this->adminSession->getUser();
    }

    /**
     * @param $orderItem
     * @return LeadInfoResponse|mixed|null
     */
    protected function getExtendLeadByOrderItem($orderItem)
    {
        $storeId = $orderItem->getStoreId();

        $leadToken = $this->getLeadToken($orderItem);
        $leadToken = reset($leadToken);
        return $this->leadInfo->getLeadInfo($leadToken, $storeId);
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return void
     */
    public function getLeftLeadsQty($orderItem)
    {
        $lead = $this->getExtendLeadByOrderItem($orderItem);
        return $lead->getLeftQty();
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return bool
     */
    public function isLeadValid($orderItem): bool
    {
        $leadInfoResponse = $this->getExtendLeadByOrderItem($orderItem);

        if (!$leadInfoResponse || $leadInfoResponse->getStatus() !== LeadInfoResponse::STATUS_LIVE) {
            return false;
        }

        $leadExpirationDate = $leadInfoResponse->getExpirationDate();
        if ($leadExpirationDate === null || time() >= $leadExpirationDate) {
            return false;
        }

        return true;
    }

    /**
     * @param CartItemInterface $quoteItem
     * @return string
     */
    public function getProductSkuByQuoteItem($quoteItem): string
    {
        return $this->warrantyRelation->getOfferQuoteItemSku($quoteItem);
    }

    /**
     * @param CartItemInterface $quoteItem
     * @return string
     */
    public function getRelationSkuByQuoteItem($quoteItem): string
    {
        return $this->warrantyRelation->getRelationQuoteItemSku($quoteItem);
    }

    /**
     * @param OrderItemInterface $orderItem
     * @return string
     */
    public function getProductSkuByOrderItem($orderItem): string
    {
        return $this->warrantyRelation->getOfferOrderItemSku($orderItem);
    }

    /**
     * Checks if order has valid lead token
     * to render offer
     *
     * @param OrderItemInterface $orderItem
     * @return bool
     */
    public function showLeadOffer($orderItem)
    {
        $result = false;

        $product = $orderItem->getProduct();
        $isWarrantyItem = $product && $product->getTypeId() === \Extend\Warranty\Model\Product\Type::TYPE_CODE;
        $leadToken = $this->getLeadToken($orderItem);

        $storeId = $orderItem->getStoreId();

        if (
            $this->isExtendEnabled($storeId)
            && $this->isOrderOffersEnabled($storeId)
            && $this->isLeadEnabled()
            && $product // product can be deleted from db
            && !$isWarrantyItem
            && !empty($leadToken)
            && $this->isLeadValid($orderItem)
        ) {
            $result = true;
        }

        return $result;
    }

    public function getProductInfo($product)
    {
        $price = $product->getFinalPrice();

        /** @var Collection $categoryCollection */
        $categoryCollection = $product->getCategoryCollection();
        $categoryCollection->addAttributeToSelect('name');
        $categoryCollection->addIsActiveFilter();
        $categoryCollection->addAttributeToSort('created_at', 'desc');
        $category = $categoryCollection->getFirstItem();

        return [
            'price' => $this->helper->formatPrice($price),
            'category' => $category && $category->getId()
                ? $category->getName()
                : \Extend\Warranty\Model\Api\Request\ProductDataBuilder::NO_CATEGORY_DEFAULT_VALUE
        ];
    }
}
