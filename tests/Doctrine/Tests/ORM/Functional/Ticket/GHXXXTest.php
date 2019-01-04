<?php

declare(strict_types=1);

namespace Doctrine\Tests\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\entityManagerInterface;
use Doctrine\Tests\OrmFunctionalTestCase;

final class GHXXXTest extends OrmFunctionalTestCase
{
    private static $TABLE_CREATED = false;

    /** @var entityManagerInterface */
    private $entityManager;

    protected function setUp() : void
    {
        parent::setUp();

        if ($this->_em->getConnection()->getDatabasePlatform()->getName() !== 'postgresql') {
            $this->markTestSkipped('Only databases supporting deferrable constraints are eligible for this test.');
        }

        $this->entityManager = $this->_getEntityManager(clone self::$_sharedConn);

        if (self::$TABLE_CREATED) {
            return;
        }

        $this->setUpEntitySchema([GHXXXEntity::class]);
        $connection = $this->entityManager->getConnection();
        $connection->exec('DROP INDEX "unique_field_constraint"');
        $connection->exec(
            'ALTER TABLE "ghxxxentity" ADD CONSTRAINT "unique_field_constraint" UNIQUE ("uniquefield") DEFERRABLE'
        );

        $this->entityManager->persist(new GHXXXEntity());
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::$TABLE_CREATED = true;
    }

    /**
     * @group XXX
     */
    public function testTransactionalWithDeferredConstraint() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->transactional(function (entityManagerInterface $entityManager) {
            $entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
            $entityManager->persist(new GHXXXEntity());
        });
    }

    /**
     * @group XXX
     */
    public function testTransactionalWithDeferredConstraintAndTransactionNesting() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->getConnection()->setNestTransactionsWithSavepoints(true);

        $this->entityManager->transactional(function (entityManagerInterface $entityManager) {
            $entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
            $entityManager->persist(new GHXXXEntity());
            $entityManager->flush();
        });
    }

    /**
     * @group XXX
     */
    public function testFlushWithDeferredConstraint() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->beginTransaction();
        $this->entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
        $this->entityManager->persist(new GHXXXEntity());
        $this->entityManager->flush();
        $this->entityManager->commit();
    }

    /**
     * @group XXX
     */
    public function testFlushWithDeferredConstraintAndTransactionNesting() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->getConnection()->setNestTransactionsWithSavepoints(true);

        $this->entityManager->beginTransaction();
        $this->entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
        $this->entityManager->persist(new GHXXXEntity());
        $this->entityManager->flush();
        $this->entityManager->commit();
    }
}

/**
 * @Entity
 * @Table(
 *     uniqueConstraints={
 *          @UniqueConstraint(columns={"uniqueField"}, name="unique_field_constraint")
 *     }
 * )
 */
class GHXXXEntity
{
    /**
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     *
     * @var int
     */
    public $id;

    /**
     * @Column(type="boolean")
     *
     * @var bool
     */
    public $uniqueField = true;
}
