<?php

declare(strict_types=1);

namespace common\models;

use frontend\components\cart\models\CartItemInterface;
use Yii;
use yii\db\ActiveRecord;

/**
 * Модель товара
 * @property string $uid Идентификатор товара
 * @property string $article Артикул товара
 * @property string $title Наименование товара
 * @property string $type Вид товара
 * @property string $mainimage_uid Идентификатор основного изображения товара
 * @property string $group_uid Идентификатор категории товара
 * @property string $measure_uid Идентификатор базовой единицы измерения
 * @property string $measuretitle Наименование базовой единицы измерения
 * @property string $measureratio Коэффициент базовой единицы измерения
 * @property string $pricegroup_uid Идентификатор ценовой группы
 * @property string $salegroup Товарная группа
 * @property string $trademark Торговая марка
 * @property string $price Оптовая цена товара
 * @property string $trademarkLogo Логотип торговой марки
 * @property bool   $disabled Флаг активного товара
 *
 * @author Mike Shatunov <mixasic@yandex.ru>
 */
class RefProduct extends ActiveRecord implements CartItemInterface {
	const ATTR_UID = 'uid';
	const ATTR_ARTICLE = 'article';
	const ATTR_TITLE = 'title';
	const ATTR_TYPE = 'type';
	const ATTR_PRICE = 'price';
	const ATTR_MAIN_IMAGE = 'mainimage_uid';
	const ATTR_MEASURE_UID = 'measure_uid';
	const ATTR_MEASURE_TITLE = 'measuretitle';
	const ATTR_MEASURE_RATIO = 'measureratio';
	const ATTR_GROUP_UID = 'group_uid';
	const ATTR_PRICEGROUP_UID = 'pricegroup_uid';
	const ATTR_SALEGROUP = 'salegroup';
	const ATTR_TRADEMARK = 'trademark';
	const ATTR_DISABLED = 'disabled';

	const LNK_REMAIN = 'remain';
	const LNK_PROPERTY = 'property';
	const LNK_CATEGORY = 'category';
	const LNK_GROUP = 'group';
	const LNK_IMAGES = 'image';
	const LNK_BARCODE = 'barcode';
	private const LNK_WAREHOUSE = 'warehouse';

	/** @var RefTrademark Логотип производителя */
	public $trademarkLogo;
	const ATTR_TRADEMARK_LOGO = 'trademarkLogo';

	/**
	 * @inheritDoc
	 *
	 * @return string название таблицы, сопоставленной с этим ActiveRecord-классом.
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public static function tableName() {
		return 'ref_product';
	}

	/**
	 * Получение группы товара
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getGroup() {

		return $this->hasMany(RefProductGroup::class, [RefProductGroup::ATTR_UID => static::ATTR_GROUP_UID]);
	}

	/**
	 * Получение торговой категории продукта
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getCategory() {

		return $this->hasMany(RefProductCategory::class, [RefProductCategory::ATTR_PRODUCT_UID => static::ATTR_UID]);
	}

	/**
	 * Получение единиц измерения
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getMeasure() {

		return $this->hasMany(RefProductMeasure::class, [RefProductMeasure::ATTR_PRODUCT_UID => static::ATTR_UID])
			->orderBy(RefProductMeasure::ATTR_RATIO . ' ASC')
			;
	}

	/**
	 * Получение изображений товара
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getImage() {

		return $this->hasMany(RefProductImage::class, [RefProductImage::ATTR_PRODUCT_UID => static::ATTR_UID]);
	}

	/**
	 * Получение основного изображения товара
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getMainImage() {
		return $this->hasOne(RefProductImage::class, [RefProductImage::ATTR_UID => static::ATTR_MAIN_IMAGE]);
	}

	/**
	 * Получение товарного остатка
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getRemain() {

		return $this->hasMany(RegProductRemain::class, [RegProductRemain::ATTR_PRODUCT_UID => static::ATTR_UID])
			->joinWith(static::LNK_WAREHOUSE, true, 'LEFT JOIN')
			;
	}

	/**
	 * Получение штрихкодов товара
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getBarcode() {

		return $this->hasMany(RegBarcode::class, [RegBarcode::ATTR_PRODUCT_UID => static::ATTR_UID]);
	}

	/**
	 * Получение свойств и значений товара
	 *
	 * @return mixed
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getProperty() {

		return $this->hasMany(RefProductProperty::class, [RefProductProperty::ATTR_PRODUCT_UID => static::ATTR_UID])->orderBy(RefProductProperty::ATTR_TITLE . ' ASC');
	}

	/**
	 * Получение цены товара
	 *
	 * @return float
	 *
	 * @author Mike Shatunov <mixasic@yandex.ru>
	 */
	public function getPartnerPrice(): float {
		if (false === Yii::$app->user->isGuest) {
			$percent = 0.0; // ОПТ по умолчанию
			$partner = Yii::$app->user->identity->partner;
			if (null !== $partner) {
				$percent = (float)$partner->priceType->percent;
				$discount = RefPartnerDiscount::findOne([RefPartnerDiscount::ATTR_PRICEGROUP_UID => $this->pricegroup_uid, RefPartnerDiscount::ATTR_PARTNER_UID => $partner->uid]);
				if (null !== $discount) {
					$percent -= $discount->percent;
				}
			}
			$price = (float)$this->price;
			if ($percent < 0) {

				return (float)round($price - ($price / 100 * abs($percent)), 2);
			}
			elseif ($percent > 0) {

				return (float)round($price + ($price / 100 * abs($percent)), 2);
			}

			return (float)round($price, 2);
		}

		$price = RefProductPrice::find()
			->where([RefProductPrice::ATTR_PRODUCT_UID => $this->uid])
			->one();

		return (float)$price->price;
	}

	/**
	 * @inheritDoc
	 */
	public function getLabel() {
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	public function getUniqueId() {

		return $this->id;
	}

	/**
	 * @inheritDoc
	 */
	public function rules() {

		return [
			[ [static::ATTR_UID, static::ATTR_ARTICLE, static::ATTR_TITLE, static::ATTR_MAIN_IMAGE], 'string'],
		];
	}

}