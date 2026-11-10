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
# NAT GATEWAY
# =====================================================

resource "aws_eip" "nat_eip" {
  domain = "vpc"

  tags = {
    Name = "gci-nat-eip"
  }
}

resource "aws_nat_gateway" "gci_nat" {
  allocation_id = aws_eip.nat_eip.id
  subnet_id     = aws_subnet.public_subnet.id

  tags = {
    Name = "gci-nat-gateway"
  }

  depends_on = [
    aws_internet_gateway.gci_igw
  ]
}

# =====================================================
# TABELA DE ROTAS PRIVADA
# =====================================================

resource "aws_route_table" "private_rt" {
  vpc_id = aws_vpc.gci_vpc.id

  route {
    cidr_block     = "0.0.0.0/0"
    nat_gateway_id = aws_nat_gateway.gci_nat.id
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