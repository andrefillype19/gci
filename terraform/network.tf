# =====================================================
# VPC
# =====================================================

resource "aws_vpc" "gci_vpc" {
  cidr_block           = var.vpc_cidr
  enable_dns_hostnames = true
  enable_dns_support   = true

  tags = {
    Name = "gci-vpc"
  }
}

# =====================================================
# INTERNET GATEWAY
# =====================================================

resource "aws_internet_gateway" "gci_igw" {
  vpc_id = aws_vpc.gci_vpc.id

  tags = {
    Name = "gci-igw"
  }
}

# =====================================================
# SUBNET PÚBLICA
# =====================================================

resource "aws_subnet" "public_subnet" {
  vpc_id                  = aws_vpc.gci_vpc.id
  cidr_block              = "10.0.1.0/24"
  map_public_ip_on_launch = true
  availability_zone       = "${var.aws_region}a"

  tags = {
    Name = "gci-public-subnet"
  }
}

# =====================================================
# SUBNET PRIVADA
# =====================================================

resource "aws_subnet" "private_subnet" {
  vpc_id                  = aws_vpc.gci_vpc.id
  cidr_block              = "10.0.2.0/24"
  map_public_ip_on_launch = false
  availability_zone       = "${var.aws_region}a"

  tags = {
    Name = "gci-private-subnet"
  }
}

# =====================================================
# TABELA DE ROTAS PÚBLICA
# =====================================================

resource "aws_route_table" "public_rt" {
  vpc_id = aws_vpc.gci_vpc.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.gci_igw.id
  }

  tags = {
    Name = "gci-public-rt"
  }
}

resource "aws_route_table_association" "public_assoc" {
  subnet_id      = aws_subnet.public_subnet.id
  route_table_id = aws_route_table.public_rt.id
}

# =====================================================
# SAÍDA PARA INTERNET DA SUBNET PRIVADA
# -----------------------------------------------------
# Sem NAT Gateway (cobrado por hora, fora do Free Tier).
# A EC2 da aplicação (subnet pública) atua como NAT
# instance: source_dest_check = false + MASQUERADE no
# user_data. Ver aws_instance.app_server.
# =====================================================

# =====================================================
# TABELA DE ROTAS PRIVADA
# =====================================================

resource "aws_route_table" "private_rt" {
  vpc_id = aws_vpc.gci_vpc.id

  route {
    cidr_block           = "0.0.0.0/0"
    network_interface_id = aws_instance.app_server.primary_network_interface_id
  }

  tags = {
    Name = "gci-private-rt"
  }
}

resource "aws_route_table_association" "private_assoc" {
  subnet_id      = aws_subnet.private_subnet.id
  route_table_id = aws_route_table.private_rt.id
}

# =====================================================
# VPC ENDPOINT PARA S3
# =====================================================

resource "aws_vpc_endpoint" "s3" {
  vpc_id            = aws_vpc.gci_vpc.id
  service_name      = "com.amazonaws.${var.aws_region}.s3"
  vpc_endpoint_type = "Gateway"

  route_table_ids = [
    aws_route_table.private_rt.id
  ]

  tags = {
    Name = "gci-s3-endpoint"
  }
}