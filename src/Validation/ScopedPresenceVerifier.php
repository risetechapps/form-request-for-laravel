<?php

namespace RiseTechApps\FormRequest\Validation;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Presence verifier that injects the registered scopes into every query built
 * by the unique and exists rules.
 *
 * Both rules funnel through the protected table() method, so overriding it is
 * enough to reach every rule string without changing any of them.
 */
class ScopedPresenceVerifier extends DatabasePresenceVerifier
{
    public function __construct(
        ConnectionResolverInterface $db,
        protected PresenceScopeRegistry $registry
    ) {
        parent::__construct($db);
    }

    /**
     * @param string $table
     * @return \Illuminate\Database\Query\Builder
     */
    #[\Override]
    protected function table($table)
    {
        $query = parent::table($table);

        if (!$this->registry->isEnabled()) {
            return $query;
        }

        foreach ($this->registry->scopesFor($table) as $scope) {
            $scope($query, $table);
        }

        return $query;
    }
}
