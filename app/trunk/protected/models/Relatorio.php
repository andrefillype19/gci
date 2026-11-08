<?php

class Relatorio extends CFormModel
{


    public $cpf_dados;
    public $matricula;

    /**
     * Declares the validation rules.
     * The rules state that username and password are required,
     * and password needs to be authenticated.
     */
    public function rules()
    {
        return array(

            array('cpf_dados, matricula', 'required'),
            array('matricula','numerical',
                'integerOnly'=>true,
                'min'=>20101060000000,
                'max'=>20999999999999),
        );

    }

    /**
     * Declares attribute labels.
     */
    public function attributeLabels()
    {
        return array(
            'cpf_dados'=>'CPF',
            'matricula'=>'Matrícula',
            'cursoNome'=>'Curso',
            'horas'=>'Horas',
        );
    }

    public function getNome(){
        $pessoa=DadosPessoais::model()->findByPk($this->cpf_dados);
        return $pessoa->nome;
    }

    public function getEntrada()
    {
        $ingresso=substr($this->matricula,0,5);
        switch ($ingresso){
            case '20111':
                return '01/02/2011';
            case '20112':
                return '01/08/2011';
            case '20121':
                return '01/02/2012';
            case '20122':
                return '01/08/2012';
            case '20131':
                return '01/02/2013';
            case '20132':
                return '01/08/2013';
            case '20141':
                return '01/02/2014';
            case '20142':
                return '01/08/2014';
            case '20151':
                return '19/02/2015';
            case '20152':
                return '03/08/2015';
            case '20161':
                return '28/03/2016';
            case '20162':
                return '16/08/2016';
            case '20171':
                return '03/04/2017';
            case '20172':
                return '21/08/2017';
            case '20181':
                return '01/02/2018';
            case '20182':
                return '01/08/2018';
            case '20191':
                return '01/02/2019';
            case '20192':
                return '01/08/2019';
            case '20201':
                return '01/02/2020';
            case '20202':
                return '18/11/2020';
            case '20211':
                return '07/04/2021';
            case '20212':
                return '01/09/2021';
            case '20221':
                return '01/03/2022';
            case '20222':
                return '08/08/2022';
            case '20231':
                return '10/02/2023';
            case '20232':
                return '01/08/2023';
            case '20241':
                return '01/02/2024';
            case '20242':
                return '10/10/2024';
            case '20251':
                return '10/04/2025';
            case '20252':
                return '01/10/2025';
            case '20261':
                return '01/03/2026';
        }
    }

    public function getCurso()
    {
        return (int)substr($this->matricula,8,2);

    }

}

