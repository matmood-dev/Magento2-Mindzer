<?php
namespace Mahmood\Hello\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Catalog\Model\ProductRepository;

class Index extends Action
{
    protected $resultRedirectFactory;
    protected $productRepository;

    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        ProductRepository $productRepository
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->productRepository = $productRepository;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $product = $this->productRepository->get('24-MB01'); // sample SKU from sample data
        $resultRedirect->setUrl($product->getProductUrl());
        return $resultRedirect;
    }
}

