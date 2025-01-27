<?php

namespace Oro\Bundle\OrderBundle\Controller;

use Oro\Bundle\AddressBundle\Entity\AddressType;
use Oro\Bundle\FormBundle\Model\UpdateHandlerFacade;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Event\OrderEvent;
use Oro\Bundle\OrderBundle\Form\Type\OrderType;
use Oro\Bundle\OrderBundle\Provider\OrderAddressSecurityProvider;
use Oro\Bundle\OrderBundle\Provider\TotalProvider;
use Oro\Bundle\OrderBundle\RequestHandler\OrderRequestHandler;
use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backend(admin) controller which handles CRUD operation for Order entity
 */
class OrderController extends AbstractController
{
    /**
     * @Route("/view/{id}", name="oro_order_view", requirements={"id"="\d+"})
     * @Template
     * @Acl(
     *      id="oro_order_view",
     *      type="entity",
     *      class="OroOrderBundle:Order",
     *      permission="VIEW",
     *      category="orders"
     * )
     */
    public function viewAction(Order $order): array
    {
        return [
            'entity' => $order,
            'totals' => $this->get(TotalProvider::class)->getTotalWithSubtotalsWithBaseCurrencyValues($order),
        ];
    }

    /**
     * @Route("/info/{id}", name="oro_order_info", requirements={"id"="\d+"})
     * @Template
     * @AclAncestor("oro_order_view")
     */
    public function infoAction(Order $order): array
    {
        if ($order->getSourceEntityClass() && $order->getSourceEntityId()) {
            $sourceEntity = $this->getDoctrine()
                ->getManagerForClass($order->getSourceEntityClass())
                ->find($order->getSourceEntityClass(), $order->getSourceEntityId());
        }

        return [
            'order' => $order,
            'sourceEntity' => $sourceEntity ?? null,
        ];
    }

    /**
     * @Route("/", name="oro_order_index")
     * @Template
     * @AclAncestor("oro_order_view")
     */
    public function indexAction(): array
    {
        return [
            'entity_class' => Order::class,
        ];
    }

    /**
     * Create order form
     *
     * @Route("/create", name="oro_order_create")
     * @Template("@OroOrder/Order/update.html.twig")
     * @Acl(
     *      id="oro_order_create",
     *      type="entity",
     *      class="OroOrderBundle:Order",
     *      permission="CREATE"
     * )
     */
    public function createAction(Request $request): array|RedirectResponse
    {
        $order = new Order();
        $order->setWebsite($this->get(WebsiteManager::class)->getDefaultWebsite());

        return $this->update($order, $request);
    }

    /**
     * Edit order form
     *
     * @Route("/update/{id}", name="oro_order_update", requirements={"id"="\d+"})
     * @ParamConverter("order", options={"repository_method" = "getOrderWithRelations"})
     * @Template
     * @Acl(
     *      id="oro_order_update",
     *      type="entity",
     *      class="OroOrderBundle:Order",
     *      permission="EDIT"
     * )
     */
    public function updateAction(Order $order, Request $request): array|RedirectResponse
    {
        return $this->update($order, $request);
    }

    protected function update(Order $order, Request $request): array|RedirectResponse
    {
        if (\in_array($request->getMethod(), ['POST', 'PUT'], true)) {
            $orderRequestHandler = $this->get(OrderRequestHandler::class);
            $order->setCustomer($orderRequestHandler->getCustomer());
            $order->setCustomerUser($orderRequestHandler->getCustomerUser());
        }

        $form = $this->createForm(OrderType::class, $order);

        return $this->get(UpdateHandlerFacade::class)->update(
            $order,
            $form,
            $this->get(TranslatorInterface::class)->trans('oro.order.controller.order.saved.message'),
            $request,
            null,
            function (Order $order, FormInterface $form, Request $request) {
                $submittedData = $request->get($form->getName());
                $event = new OrderEvent($form, $form->getData(), $submittedData);
                $this->get(EventDispatcherInterface::class)->dispatch($event, OrderEvent::NAME);
                $orderData = $event->getData()->getArrayCopy();
                $orderAddressSecurityProvider = $this->get(OrderAddressSecurityProvider::class);

                return [
                    'form' => $form->createView(),
                    'entity' => $order,
                    'isWidgetContext' => (bool)$request->get('_wid', false),
                    'isShippingAddressGranted' => $orderAddressSecurityProvider
                        ->isAddressGranted($order, AddressType::TYPE_SHIPPING),
                    'isBillingAddressGranted' => $orderAddressSecurityProvider
                        ->isAddressGranted($order, AddressType::TYPE_BILLING),
                    'orderData' => $orderData
                ];
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(parent::getSubscribedServices(), [
            WebsiteManager::class,
            OrderRequestHandler::class,
            TotalProvider::class,
            OrderAddressSecurityProvider::class,
            TranslatorInterface::class,
            EventDispatcherInterface::class,
            UpdateHandlerFacade::class
        ]);
    }
}
