<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Response;

use Magento\Payment\Gateway\Helper\ContextHelper;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use PayPal\Braintree\Gateway\Helper\SubjectReader;

class ThreeDSecureDetailsHandler implements HandlerInterface
{
    private const LIABILITY_SHIFTED = 'liabilityShifted';
    private const LIABILITY_SHIFT_POSSIBLE = 'liabilityShiftPossible';
    private const ECI_FLAG = 'eciFlag';
    private const ECI_ACCEPTED_VALUES = [
        '00' => 'Failed',
        '01' => 'Attempted',
        '02' => 'Success',
        '07' => 'Failed',
        '06' => 'Attempted',
        '05' => 'Success'
    ];

    /**
     * @var SubjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * Constructor
     *
     * @param SubjectReader $subjectReader
     */
    public function __construct(SubjectReader $subjectReader)
    {
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $this->subjectReader->readPayment($handlingSubject);
        /** @var OrderPaymentInterface $payment */
        $payment = $paymentDO->getPayment();
        ContextHelper::assertOrderPayment($payment);

        $transaction = $this->subjectReader->readTransaction($response);
        if ($payment->hasAdditionalInformation(self::LIABILITY_SHIFTED)) {
            // remove 3d secure details for reorder
            $payment->unsAdditionalInformation(self::LIABILITY_SHIFTED);
            $payment->unsAdditionalInformation(self::LIABILITY_SHIFT_POSSIBLE);
        }

        if (empty($transaction->threeDSecureInfo)) {
            return;
        }

        $info = $transaction->threeDSecureInfo;
        $payment->setAdditionalInformation(self::LIABILITY_SHIFTED, $info->liabilityShifted ? 'Yes' : 'No');
        $shiftPossible = $info->liabilityShiftPossible ? 'Yes' : 'No';
        $payment->setAdditionalInformation(self::LIABILITY_SHIFT_POSSIBLE, $shiftPossible);

        $eciFlag = $this->getEciFlagInformation($info->eciFlag);
        if ($eciFlag !== '') {
            $payment->setAdditionalInformation(self::ECI_FLAG, $eciFlag);
        }
    }

    /**
     * Get Eci Flag information
     *
     * @param string|null $eciFlagValue
     * @return string
     */
    public function getEciFlagInformation(?string $eciFlagValue = null): string
    {
        if ($eciFlagValue !== null && array_key_exists($eciFlagValue, self::ECI_ACCEPTED_VALUES)) {
            return self::ECI_ACCEPTED_VALUES[$eciFlagValue];
        }
        return '';
    }
}
