<?php

namespace App\Http\Controllers\PembuatSoal;

use App\Http\Controllers\Controller;
use App\Repositories\KategoriSoalRepository;

class KategoriController extends Controller
{
    public function __construct(
        protected KategoriSoalRepository $kategoriSoalRepository
    ) {}

    public function index()
    {
        $kategoris = $this->kategoriSoalRepository->getActive();
        return view('pembuat-soal.kategori.index', compact('kategoris'));
    }
}
