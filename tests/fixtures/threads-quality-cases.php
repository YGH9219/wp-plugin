<?php
/**
 * Small human/semantic evaluation set for the Threads copy pipeline.
 *
 * These are measured failure shapes, not production keyword rules. Automated
 * tests assert only the durable contract; release candidates can also be run
 * through the live model and reviewed against the acceptance notes.
 */

return array(
	array(
		'id'         => 'wall-mounted-tv',
		'bad'        => "⚠️ 벽걸이 TV는 벽에 달린 채로 수거받기 어려워. 수거 전에 분리해 이동 가능한 상태로 준비하는 게 첫 행동이야.\n\nTV 크기와 수량, 설치 상태 등에 따라 신청 가능 여부가 달라질 수 있으므로 예약 전에 기준도 확인해.\n\n크기·수량별 신청 조건과 벽걸이 TV 준비 기준을 원문에서 확인해 👇",
		'must_fix'   => array( '행정 안내문 문체', '습관적인 첫 이모지', '원문 확인형 메타 CTA', '근거 없는 첫 행동 우선순위' ),
		'acceptance' => '무상수거와 철거가 같지 않다는 반전 또는 벽에서 분리해야 한다는 구체적 선택을 자연스러운 피드 문장으로 전개한다.',
	),
	array(
		'id'         => 'traffic-diagnosis',
		'bad'        => "교통사고 후 한의원 치료 중 보험사의 진단서 제출 안내를 받았거나 계속 치료가 필요한 경우, 필요한 서류가 궁금해질 수 있습니다. 가장 먼저 제출기관에 제출 목적과 제출기한, 정확한 서류명을 물어봐.\n\n서류별 용도와 준비 순서를 원문에서 확인해👇",
		'must_fix'   => array( '설명형 첫 문장', '존댓말과 반말 혼용', '원문 확인형 메타 CTA' ),
		'acceptance' => '교통사고 치료·보험사 제출이라는 독자 상황을 잃지 않으면서 근거 있는 선택이나 조건으로 첫 문장을 시작한다.',
	),
	array(
		'id'         => 'numbers-and-exceptions',
		'bad'        => '기간과 예외를 생략한 채 혜택을 확정적으로 약속하는 문구',
		'must_fix'   => array( '숫자·기간·조건·예외 보존', '가능성을 확정으로 바꾸지 않음' ),
		'acceptance' => 'must_preserve의 원문 표기를 유지하고 독립 verifier가 모든 사실 단위를 supported 또는 non_factual로 판정한다.',
	),
	array(
		'id'         => 'neutral-procedure',
		'bad'        => '원문에 없는 돈·시간 손실을 만들어 억지로 자극한 문구',
		'must_fix'   => array( '근거 없는 공포 금지', '정확한 질문·선택·조건형 Hook 허용' ),
		'acceptance' => '손실 근거가 없으면 과장하지 않고 구체적인 선택·질문·조건으로 관심을 만든다.',
	),
);
