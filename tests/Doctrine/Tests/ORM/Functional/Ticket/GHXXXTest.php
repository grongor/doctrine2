<?php

declare(strict_types=1);

namespace Doctrine\Tests\Functional\Ticket;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Tests\OrmFunctionalTestCase;
use PDOException;

final class GHXXXTest extends OrmFunctionalTestCase
{
    protected function setUp() : void
    {
        parent::setUp();

        if ($this->_em->getConnection()->getDatabasePlatform()->getName() !== 'postgresql') {
            $this->markTestSkipped('This test is useful for all databases, but designed only for postgresql.');
        }

        $this->setUpEntitySchema([GHXXXEntity::class]);
        $this->_em->getConnection()->executeQuery('DROP INDEX "unique_field_constraint"');
        $this->_em->getConnection()->executeQuery(
            'ALTER TABLE "ghxxxentity" ADD CONSTRAINT "unique_field_constraint" UNIQUE ("uniquefield") DEFERRABLE'
        );
    }

    /**
     * @group XXX
     */
    public function testTransactionalWithDeferredConstraint() : void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage(<<<HEREDOC
SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint "unique_field_constraint"
DETAIL:  Key (uniquefield)=(t) already exists.
HEREDOC
        );

        $entity1              = new GHXXXEntity();
        $entity1->uniqueField = true;

        $this->_em->persist($entity1);
        $this->_em->flush();
        $this->_em->clear();

        $this->_em->transactional(function (EntityManagerInterface $entityManager) {
            $entityManager->getConnection()->executeQuery('SET CONSTRAINTS "unique_field_constraint" DEFERRED');

            $entity2              = new GHXXXEntity();
            $entity2->uniqueField = true;
            $entityManager->persist($entity2);
        });
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
    public $uniqueField;
}
