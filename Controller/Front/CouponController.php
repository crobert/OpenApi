<?php

namespace OpenApi\Controller\Front;

use OpenApi\Model\Api\Coupon;
use OpenApi\Model\Api\ModelFactory;
use OpenApi\OpenApi;
use OpenApi\Service\OpenApiService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Coupon\CouponConsumeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\EventDispatcher\EventDispatcher;
use Thelia\Core\HttpFoundation\Request;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CouponQuery;

/**
 * @Route("/coupon", name="coupon")
 */
class CouponController extends BaseFrontOpenApiController
{
    /**
     * @Route("", name="submit_coupon", methods="POST")
     *
     * @OA\Post(
     *     path="/coupon",
     *     tags={"coupon"},
     *     summary="Submit a coupon",
     *
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="code",
     *                     type="string"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/Coupon")
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad request",
     *          @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function submitCoupon(
        Request $request,
        EventDispatcherInterface $dispatcher,
        ModelFactory $modelFactory
    )
    {
        $cart = $request->getSession()->getSessionCart($dispatcher);
        if (null === $cart) {
            throw new \Exception(Translator::getInstance()->trans('No cart found', [], OpenApi::DOMAIN_NAME));
        }

        /** @var Coupon $openApiCoupon */
        $openApiCoupon = $modelFactory->buildModel('Coupon', $request->getContent());
        if (null === $openApiCoupon->getCode()) {
            throw new \Exception(Translator::getInstance()->trans('Coupon code cannot be null', [], OpenApi::DOMAIN_NAME));
        }

        /** We verify that the given coupon actually exists in the base */
        $theliaCoupon = CouponQuery::create()->filterByCode($openApiCoupon->getCode())->findOne();
        if (null === $theliaCoupon) {
            throw new \Exception(Translator::getInstance()->trans('No coupons were found for this coupon code.', [], OpenApi::DOMAIN_NAME));
        }

        $event = new CouponConsumeEvent($openApiCoupon->getCode());
        $dispatcher->dispatch($event,TheliaEvents::COUPON_CONSUME);
        $openApiCoupon = $modelFactory->buildModel('Coupon', $theliaCoupon);

        return OpenApiService::jsonResponse($openApiCoupon);
    }
}
