<?php /* Підказка під датами (0.5.19): чи вільне обране місце на ці дні, а якщо ні — з якого дня. Рахує assets/js/banners.js.
  $occupancy — App\Core\Ads::occupancy(). */
$data = ['today' => App\Core\Ads::today(), 'limits' => [], 'busy' => []];
foreach ($occupancy as $pl => $o) { $data['limits'][$pl] = (int) $o['limit']; $data['busy'][$pl] = array_map(static fn ($p) => [$p[0], $p[1]], $o['periods']); } ?>
<div class="ad-free" id="adFree" data-occ="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>" aria-live="polite" hidden></div>
