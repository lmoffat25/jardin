<?php

// V3 : la route de santé répond sans session, après avoir interrogé la base.

it('rend 200 et {"etat":"ok"} sans session', function () {
    $reponse = $this->getJson('/sante');

    $reponse->assertOk();
    expect($reponse->getContent())->toBe('{"etat":"ok"}');
    expect(auth()->check())->toBeFalse();
});
