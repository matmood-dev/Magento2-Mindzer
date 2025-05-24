<?php
namespace Mahmood\Hello\Block;

use Magento\Framework\View\Element\Template;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class RandomProduct extends Template
{
    protected $productCollectionFactory;

    public function __construct(
        Template\Context $context,
        CollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $data);
    }

    public function getRandomProduct()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*')
                   ->addAttributeToFilter('visibility', ['neq' => 1]) // exclude Not Visible Individually
                   ->addAttributeToFilter('status', 1)
                   ->setPageSize(1)
                   ->getSelect()
                   ->order('RAND()');

        return $collection->getFirstItem();
    }
}

