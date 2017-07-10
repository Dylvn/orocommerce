<?php

namespace Oro\Bundle\ShoppingListBundle\Controller\Frontend\Api\Rest;

use FOS\RestBundle\Controller\Annotations\NamePrefix;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\Put;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use Oro\Bundle\EntityBundle\Exception\FieldUpdateAccessException;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\ShoppingListBundle\Manager\ShoppingListManager;
use Oro\Bundle\SoapBundle\Controller\Api\Rest\RestController;

/**
 * @NamePrefix("oro_api_")
 */
class ShoppingListController extends RestController implements ClassResourceInterface
{
    /**
     * @Put("/shoppinglists/current/{id}")
     *
     * @ApiDoc(
     *      description="Set current Shopping List",
     *      resource=true
     * )
     * @AclAncestor("oro_shopping_list_frontend_update")
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function setCurrentAction($id)
    {
        /** @var ShoppingListManager $manager */
        $manager = $this->get('oro_shopping_list.shopping_list.manager');

        $shoppingList = $this->get('oro_shopping_list.repository.shopping_list')->find($id);

        if ($shoppingList === null) {
            throw $this->createNotFoundException('Can\'t find shopping list with id ' . $id);
        }
        $isGranted = $this->isGranted('EDIT', $shoppingList);
        $isProcessed = false;
        $view = $this->view([], Codes::HTTP_NO_CONTENT);
        if (!$isGranted) {
            $view = $this->view(['reason' => 'Access denied'], Codes::HTTP_FORBIDDEN);
            $isProcessed = true;
        }
        $manager->setCurrent($this->getUser(), $shoppingList);

        return $this->buildResponse($view, self::ACTION_UPDATE, ['id' => $id, 'success' => $isProcessed]);
    }

    /**
     * @Put("/shoppinglists/{id}/owner")
     *
     * @ApiDoc(
     *      description="Set Shopping List Owner",
     *      resource=true
     * )
     * @AclAncestor("oro_shopping_list_frontend_assign")
     *
     * @param Request $request
     * @param ShoppingList $shoppingList
     * @return JsonResponse
     */
    public function setOwnerAction(Request $request, ShoppingList $shoppingList)
    {
        $manager = $this->get('oro_shopping_list.shopping_list.owner_manager');
        $status = Response::HTTP_OK;
        $data = $this->container->get('translator')
            ->trans(
                'oro.shoppinglist.flash.update_success',
                ['%shoppinglist%' => $shoppingList->getLabel()]
            );
        try {
            $ownerId = $request->request->get("ownerId");
            $manager->setOwner($ownerId, $shoppingList);
        } catch (AccessDeniedException $e) {
            $status = Response::HTTP_FORBIDDEN;
        } catch (\InvalidArgumentException $e) {
            $status = Response::HTTP_BAD_REQUEST;
            $data = $e->getMessage();
        }

        return new JsonResponse($data, $status);
    }

    /**
     * @param Request $request
     * @param ShoppingList $shoppingList
     *
     * @return Response
     *
     * @Patch("shoppinglists/{id}")
     * @ApiDoc(
     *      description="Update shopping List property",
     *      resource=true,
     *      requirements = {
     *          {"name"="id", "dataType"="integer"},
     *      }
     * )
     */
    public function patchAction(Request $request, ShoppingList $shoppingList)
    {
        $data = json_decode($request->getContent(), true);

        if (!$this->get('oro_shopping_list.customer_visitor.authorization_checker')->isGranted('EDIT', $shoppingList)) {
            throw $this->createAccessDeniedException();
        }

        try {
            list($form, $data) = $this->get('oro_entity.manager.entity_field_manager')->update($shoppingList, $data);
        } catch (FieldUpdateAccessException $e) {
            throw $this->createAccessDeniedException($e->getMessage(), $e);
        }

        if ($form->getErrors(true)->count() > 0) {
            $view = $this->view($form, Codes::HTTP_BAD_REQUEST);
        } else {
            $statusCode = !empty($data) ? Codes::HTTP_OK : Codes::HTTP_NO_CONTENT;
            $view = $this->view($data, $statusCode);
        }

        return parent::handleView($view);
    }

    /**
     * {@inheritdoc}
     */
    public function getManager()
    {
        return $this->get('oro_shopping_list.shopping_list.manager.api');
    }

    /**
     * {@inheritdoc}
     */
    public function getForm()
    {
        throw new \LogicException('This method should not be called');
    }

    /**
     * {@inheritdoc}
     */
    public function getFormHandler()
    {
        throw new \LogicException('This method should not be called');
    }
}
