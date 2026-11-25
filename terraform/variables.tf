variable "aws_region" {
  description = "Regiao AWS"
  type        = string
  default     = "us-east-2"
}

variable "key_name" {
  description = "Chave SSH da instancia da aplicacao"
  type        = string
  default     = "key-gci"
}

variable "app_instance_type" {
  description = "Tipo da EC2 da aplicacao"
  type        = string
  default     = "t3.small"
}

variable "db_instance_type" {
  description = "Tipo da EC2 PostgreSQL"
  type        = string
  default     = "m7i-flex.large"
}

variable "vpc_cidr" {
  description = "CIDR da VPC"
  type        = string
  default     = "10.0.0.0/16"
}

variable "db_private_ip" {
  description = "IP privado fixo da EC2 PostgreSQL (usado como DB_HOST no k3s)"
  type        = string
  default     = "10.0.2.153"
}

variable "db_name" {
  description = "Nome do banco restaurado a partir do gci.sql"
  type        = string
  default     = "gci-test-db"
}

variable "db_password" {
  description = "Senha do usuario postgres (deve bater com DB_PASSWORD do Secret do k3s)"
  type        = string
  default     = "admin"
  sensitive   = true
}

variable "nfs_instance_type" {
  description = "Tipo da EC2 do servidor NFS"
  type        = string
  default     = "t3.micro"
}

variable "nfs_private_ip" {
  description = "IP privado fixo da EC2 NFS (usado nos PVs em deploy/common/nfs-pv.yaml)"
  type        = string
  default     = "10.0.2.60"
}

variable "efs_private_ip" {
  description = "IP privado fixo do mount target do EFS (usado nos PVs em deploy/common/efs-pv.yaml)"
  type        = string
  default     = "10.0.2.70"
}
