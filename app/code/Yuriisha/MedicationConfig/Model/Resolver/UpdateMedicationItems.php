<?php
declare(strict_types=1);

namespace Yuriisha\MedicationConfig\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\Resolver\ArgumentsProcessorInterface;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

class UpdateMedicationItems implements ResolverInterface
{
    /**
     * @param GetCartForUser $getCartForUser
     * @param CartRepositoryInterface $cartRepository
     * @param ArgumentsProcessorInterface $argsSelection
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ArgumentsProcessorInterface $argsSelection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $processedArgs = $this->argsSelection->process($info->fieldName, $args);

        if (empty($processedArgs['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }

        $maskedCartId = $processedArgs['input']['cart_id'];

        $errors = [];
        if (empty($processedArgs['input']['cart_items'])
            || !is_array($processedArgs['input']['cart_items'])
        ) {
            $message = 'Required parameter "cart_items" is missing.';
            $errors[] = [
                'message' => __($message)
            ];
        }

        $cartItems = $processedArgs['input']['cart_items'];
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        try {
            $this->processCartItems($cart, $cartItems);
            $this->cartRepository->save(
                $this->cartRepository->get((int)$cart->getId())
            );
        } catch (NoSuchEntityException|LocalizedException $e) {
            $message = (str_contains($e->getMessage(), 'The requested qty is not available'))
                ? 'The requested qty. is not available'
                : $e->getMessage();
            $errors[] = [
                'message' => __($message)
            ];
        }

        return [
            'cart' => [
                'model' => $cart,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * @param Quote $cart
     * @param array $items
     * @return void
     * @throws GraphQlInputException
     * @throws GraphQlNoSuchEntityException
     */
    private function processCartItems(Quote $cart, array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['cart_item_id'])) {
                throw new GraphQlInputException(__('Required parameter "cart_item_id" for "cart_items" is missing.'));
            }

            $itemId = (int)$item['cart_item_id'];
            $cartItem = $cart->getItemById($itemId);

            if ($cartItem && $cartItem->getParentItemId()) {
                throw new GraphQlInputException(__('Child items may not be updated.'));
            }

            $cartItem = $cart->getItemById($itemId);
            if ($cartItem === false) {
                throw new GraphQlNoSuchEntityException(
                    __('Could not find cart item with id: %1.', $itemId)
                );
            }
            if (isset($item['medication_product_data'])) {
                $cartItem->setAdditionalData($item['medication_product_data']);
            }
        }
    }
}
