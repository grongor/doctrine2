<?php

declare(strict_types=1);

namespace Doctrine\Tests\Functional\Ticket;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Tests\OrmFunctionalTestCase;

/**
 * @see https://github.com/doctrine/orm/issues/7555
 */
final class GH7555Test extends OrmFunctionalTestCase
{
    private static $tableCreated = false;

    /** @var EntityManagerInterface */
    private $entityManager;

    protected function setUp() : void
    {
        parent::setUp();

        if ($this->em->getConnection()->getDatabasePlatform()->getName() !== 'postgresql') {
            $this->markTestSkipped('Only databases supporting deferrable constraints are eligible for this test.');
        }

        $this->entityManager = $this->getEntityManager(clone self::$sharedConn);

        if (self::$tableCreated) {
            return;
        }

        $this->setUpEntitySchema([GH7555Entity::class]);
        $connection = $this->entityManager->getConnection();
        $connection->exec('DROP INDEX "unique_field_constraint"');
        $connection->exec(
            'ALTER TABLE "gh7555entity" ADD CONSTRAINT "unique_field_constraint" UNIQUE ("uniquefield") DEFERRABLE'
        );

        $this->entityManager->persist(new GH7555Entity());
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::$tableCreated = true;
    }

    /**
     * @group GH7555
     */
    public function testTransactionalWithDeferredConstraint() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->transactional(static function (EntityManagerInterface $entityManager) : void {
            $entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
            $entityManager->persist(new GH7555Entity());
        });
    }

    /**
     * @group GH7555
     */
    public function testTransactionalWithDeferredConstraintAndTransactionNesting() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->getConnection()->setNestTransactionsWithSavepoints(true);

        $this->entityManager->transactional(static function (EntityManagerInterface $entityManager) : void {
            $entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
            $entityManager->persist(new GH7555Entity());
            $entityManager->flush();
        });
    }

    /**
     * @group GH7555
     */
    public function testFlushWithDeferredConstraint() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->beginTransaction();
        $this->entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
        $this->entityManager->persist(new GH7555Entity());
        $this->entityManager->flush();
        $this->entityManager->commit();
    }

    /**
     * @group GH7555
     */
    public function testFlushWithDeferredConstraintAndTransactionNesting() : void
    {
        $this->expectException(DBALException::class);
        $this->expectExceptionMessage('violates unique constraint "unique_field_constraint"');

        $this->entityManager->getConnection()->setNestTransactionsWithSavepoints(true);

        $this->entityManager->beginTransaction();
        $this->entityManager->getConnection()->exec('SET CONSTRAINTS "unique_field_constraint" DEFERRED');
        $this->entityManager->persist(new GH7555Entity());
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
class GH7555Entity
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
