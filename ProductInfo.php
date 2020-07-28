<?php

declare(strict_types=1);

namespace frontend\models;

use common\models\RefProductProperty;
use common\models\RefTrademark;
use common\models\RefProductPrice;
use common\models\RefProduct;
use common\models\RegProductRemain;
use common\models\RefWarehouse;
use yii\data\ArrayDataProvider;

/**
 * Модель поиска товарных аналогов
 *
 * @author Mike Shatunov <mixasic@yandex.ru>
 */
class ProductInfo {
	/**
	 * Получение информации о товаре.
	 *
	 * @param string $product_uid Идентификатор товара.
	 *
	 * @return RefProduct
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getProductInfo(string $product_uid): RefProduct {
		$product = RefProduct::find()
			->with(RefProduct::LNK_PROPERTY)
			->where([RefProduct::ATTR_UID => $product_uid])
			->one()
		;

		$product->trademarkLogo = RefTrademark::find()
			->where("LOWER(" . RefTrademark::ATTR_TITLE . ")='" . mb_strtolower($product->trademark . "'"))
			->one()
		;
		;/** @var RefProduct $product Товар */

		return $product;
	}

	/**
	 * Поиск товарных аналогов
	 *
	 * @param string $product_uid Идентификатор товара
	 *
	 * @return ArrayDataProvider
	 */
	public function getAnalogs(string $product_uid): ArrayDataProvider {
		$product = $this->getProductInfo($product_uid);
		$analogs = RefProduct::find()
			->with(RefProduct::LNK_GROUP)
			->joinWith(RefProduct::LNK_REMAIN, true, 'LEFT JOIN')
			->where([RefProduct::ATTR_DISABLED => false])
			->andWhere(['type' => $product[RefProduct::ATTR_TYPE]])
			->andWhere(['<>', RefProduct::ATTR_TRADEMARK, $product->trademark])
			->andWhere(['>', RefProduct::ATTR_PRICE, 0])
			->orderBy(
				'(CASE WHEN ' . RefWarehouse::tableName() . '.' . RefWarehouse::ATTR_IS_PRIVATE . '=true THEN 1 ELSE NULL END) DESC NULLS LAST,' .
				RegProductRemain::tableName() . '.' . RegProductRemain::ATTR_REMAIN . ' DESC' . ',' .
				RefProduct::ATTR_PRICE . ' DESC'
			)
			->all()
		;

		$analogs = array_filter($analogs, function($item) {
			if ($item->partnerPrice > 0) {
				return $item;
			}
		});

		/** @var RefProduct $item */
		foreach ($analogs as &$item) {
			$item->trademarkLogo = RefTrademark::find()
				->where(['title' => $item->trademark])
				->one()
			;
		}

		return new ArrayDataProvider([
			'allModels' => $analogs,
			'pagination' => [
				'pageSize' => 60,
			],
		]);
	}
}
