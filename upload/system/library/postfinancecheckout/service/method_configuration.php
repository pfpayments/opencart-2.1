<?php

namespace PostFinanceCheckout\Service;

class MethodConfiguration extends AbstractService {

	/**
	 * Updates the data of the payment method configuration.
	 *
	 * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
	 */
	public function updateData(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration){
		/* @var \PostFinanceCheckout\Entity\MethodConfiguration $entity */
		$entity = \PostFinanceCheckout\Entity\MethodConfiguration::loadByConfiguration($this->registry, $configuration->getLinkedSpaceId(), $configuration->getId());
		if ($entity->getId() !== null && $this->hasChanged($configuration, $entity)) {
			$entity->setConfigurationName($configuration->getName());
			$entity->setTitle($configuration->getResolvedTitle());
			$entity->setDescription($configuration->getResolvedDescription());
			$entity->setImage($configuration->getResolvedImageUrl());
			$entity->setSortOrder($configuration->getSortOrder());
			$entity->save();
		}
	}

	private function hasChanged(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration, \PostFinanceCheckout\Entity\MethodConfiguration $entity){
		if ($configuration->getName() != $entity->getConfigurationName()) {
			return true;
		}
		
		if ($configuration->getResolvedTitle() != $entity->getTitle()) {
			return true;
		}
		
		if ($configuration->getResolvedDescription() != $entity->getDescription()) {
			return true;
		}
		
		if ($configuration->getResolvedImageUrl() != $entity->getImage()) {
			return true;
		}
		
		if ($configuration->getSortOrder() != $entity->getSortOrder()) {
			return true;
		}
		
		return false;
	}

	/**
	 * Synchronizes the payment method configurations from PostFinanceCheckout.
	 */
	public function synchronize($space_id){
		$existing_found = array();
		$existing_configurations = \PostFinanceCheckout\Entity\MethodConfiguration::loadBySpaceId($this->registry, $space_id);
		
		$payment_method_configuration_service = new \Wallee\Sdk\Service\PaymentMethodConfigurationService(
				\PostFinanceCheckoutHelper::instance($this->registry)->getApiClient());
		$configurations = $payment_method_configuration_service->search($space_id, new \Wallee\Sdk\Model\EntityQuery());
		
		foreach ($configurations as $configuration) {
			$method = \PostFinanceCheckout\Entity\MethodConfiguration::loadByConfiguration($this->registry, $space_id, $configuration->getId());
			if ($method->getId() !== null) {
				$existing_found[] = $method->getId();
			}
			
			$method->setSpaceId($space_id);
			$method->setConfigurationId($configuration->getId());
			$method->setConfigurationName($configuration->getName());
			$method->setState($this->getConfigurationState($configuration));
			$method->setTitle($configuration->getResolvedTitle());
			$method->setDescription($configuration->getResolvedDescription());
			$method->setImage($configuration->getResolvedImageUrl());
			$method->setSortOrder($configuration->getSortOrder());
			$method->save();
		}
		
		foreach ($existing_configurations as $existing_configuration) {
			if (!in_array($existing_configuration->getId(), $existing_found)) {
				$existing_configuration->setState(\PostFinanceCheckout\Entity\MethodConfiguration::STATE_HIDDEN);
				$existing_configuration->save();
			}
		}
		
		\PostFinanceCheckout\Provider\PaymentMethod::instance($this->registry)->clearCache();
	}

	/**
	 * Returns the payment method for the given id.
	 *
	 * @param int $id
	 * @return \Wallee\Sdk\Model\PaymentMethod
	 */
	protected function getPaymentMethod($id){
		return \PostFinanceCheckout\Provider\PaymentMethod::instance($this->registry)->find($id);
	}

	/**
	 * Returns the state for the payment method configuration.
	 *
	 * @param \Wallee\Sdk\Model\PaymentMethodConfiguration $configuration
	 * @return string
	 */
	protected function getConfigurationState(\Wallee\Sdk\Model\PaymentMethodConfiguration $configuration){
		switch ($configuration->getState()) {
			case \Wallee\Sdk\Model\CreationEntityState::ACTIVE:
				return \PostFinanceCheckout\Entity\MethodConfiguration::STATE_ACTIVE;
			case \Wallee\Sdk\Model\CreationEntityState::INACTIVE:
				return \PostFinanceCheckout\Entity\MethodConfiguration::STATE_INACTIVE;
			default:
				return \PostFinanceCheckout\Entity\MethodConfiguration::STATE_HIDDEN;
		}
	}
}