<?php
/**
 * Extend Warranty
 *
 * @author      Extend Magento Team <magento@guidance.com>
 * @category    Extend
 * @package     Warranty
 * @copyright   Copyright (c) 2021 Extend Inc. (https://www.extend.com/)
 */

namespace Extend\Warranty\Cron;

use Extend\Warranty\Helper\Api\Data as DataHelper;
use Extend\Warranty\Model\ContractCreateProcess;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class CreateContracts
 *
 * Create Contracts Cron
 */
class CreateContracts
{
    /**
     * Contract Create Process Model
     *
     * @var ContractCreateProcess
     */
    private $contractCreateProcess;

    /**
     * Warranty Api Helper
     *
     * @var DataHelper
     */
    private $dataHelper;

    /**
     * CreateContracts constructor
     *
     * @param ContractCreateProcess $contractCreateProcess
     * @param DataHelper $dataHelper
     */
    public function __construct(
        ContractCreateProcess $contractCreateProcess,
        DataHelper $dataHelper
    ) {
        $this->contractCreateProcess = $contractCreateProcess;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Create warranty contracts
     */
    public function execute()
    {
        if (!$this->dataHelper->isExtendEnabled(ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
            || !$this->dataHelper->isWarrantyContractEnabled()
        ) {
            return;
        }

        $this->contractCreateProcess->execute();
    }
}
