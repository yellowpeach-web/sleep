<?php

namespace WPML\EditHistory;

interface EntryLog {

	public function append( Entry $entry ): int;

	public function since( Subject $subject, string $language, int $afterId ): array;

	public function size( Subject $subject ): array;

	public function subjectsWithEntriesBefore( int $time, int $limit, ?Subject $after = null ): array;

	public function delete( Subject $subject, int $entryId ): int;

	public function deleteUpTo( Subject $subject, int $entryId ): int;

	public function deleteSubject( Subject $subject ): int;
}
