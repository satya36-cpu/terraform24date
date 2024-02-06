<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Controller\Adminhtml\Contract;

use Extend\Warranty\Model\Config\Source\CreateContractApi;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Extend\Warranty\Helper\FloatComparator;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Extend\Warranty\Model\Api\Sync\Contract\ContractsRequest as ApiContractModel;
use Extend\Warranty\Model\WarrantyContract as WarrantyContractModel;
use Extend\Warranty\Model\Api\Sync\Orders\RefundRequest as OrdersApiRefund;
use Extend\Warranty\Helper\Data as Helper;
use Extend\Warranty\Helper\Api\Data as DataHelper;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\ScopeInterface;
use Exception;

/**
 * Class Refund
 *
 * Refund Contract Adminhtml Controller
 */
class Refund extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    public const ADMIN_RESOURCE = 'Extend_Warranty::refund_warranty';

    /**
     * API Contract
     *
     * @var ApiContractModel
     */
    private $apiContractModel;

    /**
     * Warranty Contract
     *
     * @var WarrantyContractModel
     */
    private $warrantyContractModel;

    /**
     * Orders Refund
     *
     * @var OrdersApiRefund
     */
    private $ordersApiRefund;

    /**
     * Warranty Api Helper
     *
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * Warranty Helper
     *
     * @var Helper
     */
    private $helper;

    /**
     * Float Comparator Model
     *
     * @var FloatComparator
     */
    private $floatComparator;

    /**
     * Serializer
     *
     * @var Json
     */
    private $jsonSerializer;

    /**
     * Order Item Repository Model
     *
     * @var OrderItemRepositoryInterface
     */
    private $orderItemRepository;

    /**
     * Logger Model
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Refund constructor
     *
     * @param Context $context
     * @param ApiContractModel $apiContractModel
     * @param WarrantyContractModel $warrantyContractModel
     * @param OrdersApiRefund $ordersApiRefund
     * @param DataHelper $dataHelper
     * @param Helper $helper
     * @param FloatComparator $floatComparator
     * @param Json $jsonSerializer
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        ApiContractModel $apiContractModel,
        WarrantyContractModel $warrantyContractModel,
        OrdersApiRefund $ordersApiRefund,
        DataHelper $dataHelper,
        Helper $helper,
        FloatComparator $floatComparator,
        Json $jsonSerializer,
        OrderItemRepositoryInterface $orderItemRepository,
        LoggerInterface $logger
    ) {
        $this->apiContractModel = $apiContractModel;
        $this->warrantyContractModel = $warrantyContractModel;
        $this->ordersApiRefund = $ordersApiRefund;
        $this->dataHelper = $dataHelper;
        $this->helper = $helper;
        $this->floatComparator = $floatComparator;
        $this->jsonSerializer = $jsonSerializer;
        $this->orderItemRepository = $orderItemRepository;
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Validate a refund and report a contract cancellation
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $request = $this->getRequest();
        $orderItemId = $request->getParam('itemId');
        $orderItem = $this->getOrderItem($orderItemId);
        if ($orderItem) {
            $storeId = (int)$orderItem->getStoreId();
            if (!$this->dataHelper->isExtendEnabled(ScopeInterface::SCOPE_STORES, $storeId)
                || !$this->dataHelper->isRefundEnabled($storeId)
            ) {
                $data = [
                    'success'   => false,
                    'error'     => __('Extension or refunds are not enabled.'),
                ];
                $resultJson->setData($data);

                return $resultJson;
            }

            $apiUrl = $this->dataHelper->getApiUrl(ScopeInterface::SCOPE_STORES, $storeId);
            $apiStoreId = $this->dataHelper->getStoreId(ScopeInterface::SCOPE_STORES, $storeId);
            $apiKey = $this->dataHelper->getApiKey(ScopeInterface::SCOPE_STORES, $storeId);

            try {
                if ($this->dataHelper->getContractCreateApi(ScopeInterface::SCOPE_STORES, $storeId) ==
                    CreateContractApi::ORDERS_API
                ) {
                    $this->ordersApiRefund->setConfig($apiUrl, $apiStoreId, $apiKey);
                }
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
                $data = [
                    'success'   => false,
                    'error'     => __($exception->getMessage()),
                ];
                $resultJson->setData($data);

                return $resultJson;
            }

            $validateRefund = (bool)$request->getParam('validation');
            if ($validateRefund) {
                $validationResult = $this->validateRefund($storeId);
                $resultJson->setData($validationResult);

                return $resultJson;
            }

            $refundResult = $this->refund($orderItem, $storeId);
            $resultJson->setData($refundResult);

            return $resultJson;
        } else {
            $data = [
                'success'   => false,
                'error'     => __('Can\'t get order item.'),
            ];
            $resultJson->setData($data);

            return $resultJson;
        }
    }

    /**
     * Get order item
     *
     * @param int $orderItemId
     * @return OrderItemInterface|null
     */
    private function getOrderItem(int $orderItemId)
    {
        try {
            $orderItem = $this->orderItemRepository->get($orderItemId);
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            $orderItem = null;
        }

        return $orderItem;
    }

    /**
     * Get preview of the cancellation, including the amount that would be refunded
     *
     * @param int $storeId
     * @return array
     */
    private function validateRefund(int $storeId): array
    {
        $request = $this->getRequest();
        $contractIds = $request->getParam('contractId') ?? [];

        try {
            $amountValidated = 0;
            foreach ($contractIds as $contractId) {
                if ($this->dataHelper->getContractCreateApi(ScopeInterface::SCOPE_STORES, $storeId) ==
                    CreateContractApi::ORDERS_API
                ) {
                    $refundData = $this->ordersApiRefund->validateRefund($contractId);
                    if (isset($refundData['refundAmounts'])
                        && isset($refundData['refundAmounts']['customer'])
                        && $this->floatComparator->greaterThan(
                            (float)$refundData['refundAmounts']['customer']
                            , 0
                        )
                    ) {
                        $amountValidated += $refundData['refundAmounts']['customer'];
                    }
                }
            }
            $data = [
                'success'           => true,
                'amountValidated'   => $this->helper->removeFormatPrice($amountValidated),
            ];
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            $data = [
                'success'   => false,
                'error'     => __($exception->getMessage()),
            ];
        }

        return $data;
    }

    /**
     * Request a refund
     *
     * @param OrderItemInterface $orderItem
     * @param int $storeId
     *
     * @return array
     */
    private function refund(OrderItemInterface $orderItem, int $storeId): array
    {
        $contractIds = $this->warrantyContractModel->getContractIds($orderItem);
        $options['refund_responses_log'] = [];

        $request = $this->getRequest();
        $refundedContractIds = $request->getParam('contractId') ?? [];

        try {
            foreach ($refundedContractIds as $contractId) {
                if ($this->dataHelper->getContractCreateApi(ScopeInterface::SCOPE_STORES, $storeId) ==
                    CreateContractApi::ORDERS_API
                ) {
                    $status = $this->ordersApiRefund->refund($contractId);
                }
                $options['refund_responses_log'][] = [
                    'contract_id' => $contractId,
                    'response' => $status,
                ];

                if ($status) {
                    $key = array_search($contractId, $contractIds);
                    unset($contractIds[$key]);
                }
            }

            if (!empty($options['refund_responses_log'])) {
                try {
                    $contractIdsJson = $this->jsonSerializer->serialize($contractIds);
                    $orderItem->setContractId($contractIdsJson);
                    $options['refund'] = empty($contractIds);
                    $orderItem = $this->warrantyContractModel->updateOrderItemOptions($orderItem, $options);
                    $this->orderItemRepository->save($orderItem);
                } catch (LocalizedException $exception) {
                    $this->logger->error($exception->getMessage());
                    $data = [
                        'success'   => false,
                        'error'     => __($exception->getMessage()),
                    ];
                }
            }

            $data['success'] = true;
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            $data = [
                'success'   => false,
                'error'     => __($exception->getMessage()),
            ];
        }

        return $data;
    }
}
