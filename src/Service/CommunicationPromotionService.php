<?php

namespace App\Service;

use App\Entity\CommunicationClientPackage;
use App\Entity\CommunicationProduct;
use App\Entity\CommunicationPromotions;
use App\Service\CommonService;

class CommunicationPromotionService extends CommonService
{
    /**
     * @param array $inParams
     * @return \App\Entity\CommunicationPromotions
     * @throws \Exception
     */
    public function onCreatedPromotion(array $inParams): CommunicationPromotions
    {
        $params = (object)$inParams;
        $promotion = new CommunicationPromotions();
        $promotion->setName($params->name);
        $productPromotion = $this->em->getRepository(CommunicationProduct::class)->find($params->productId);
        if (!is_null($productPromotion)) {
            $promotion->setProduct($productPromotion);
        }
        $promotion->setDescription($params->description);
        $promotion->setInfoDescription($params->infoDescription);
        $promotion->setKnowMore($params->knowMore);
        $promotion->setTerms($params->terms);
        $startDateAt = new \DateTimeImmutable($params->range['startAt']);
        $endDateAt = new \DateTimeImmutable($params->range['endAt']);
        $promotion->setStartAt($startDateAt);
        $promotion->setEndAt($endDateAt);


        foreach ($params->products as $key => $item) {
            $productId = $item['productId'];
            $product = $this->em->getRepository(CommunicationClientPackage::class)->find($productId);
            if (!is_null($product)) {
                $promotion->addProduct($product);
            }
        }
        $this->em->persist($promotion);
        $this->em->flush();

        return $promotion;
    }
}
